<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MasterBuilder extends Command
{
    protected $signature = 'builder:master {modelName} {fields} {processId} {--soft-deletes} {--generate-factory-seeder} {--generate-view}';
    protected $description = 'Master command to orchestrate model, migration, and resource generation';

    public function handle()
    {
        $modelName = $this->argument('modelName');
        $fieldsJson = $this->argument('fields');
        $processId = $this->argument('processId');
        $softDeletes = $this->option('soft-deletes');
        $generateFactorySeeder = $this->option('generate-factory-seeder');
        $generateView = $this->option('generate-view');
        
        $resultFile = storage_path('app/builder_result_' . $processId . '.json');
        
        try {
            $fields = json_decode($fieldsJson, true);
            
            if (!$fields || !is_array($fields)) {
                $this->writeResult($resultFile, 'error', 'Invalid fields format');
                return 1;
            }

            $this->info("Starting generation process for model: {$modelName}");

            // Calculate total steps
            $totalSteps = 4; // Base steps: model, migration, migrate, resource
            if ($generateFactorySeeder) $totalSteps++;

            $currentStep = 0;

            // Step 1: Generate and Modify Model
            $currentStep++;
            $this->info("Step {$currentStep}/{$totalSteps}: Generating model...");
            $stepId = uniqid();
            
            Artisan::call('builder:generate-model', [
                'modelName' => $modelName,
                'fields' => $fieldsJson,
                'stepId' => $stepId,
                '--soft-deletes' => $softDeletes
            ]);
            
            $modelResult = $this->readStepResult($stepId);
            
            if (!$modelResult || !$modelResult['success']) {
                $message = $modelResult['message'] ?? 'Model generation failed';
                $this->error("Model generation failed: {$message}");
                $this->writeResult($resultFile, 'error', "Model generation failed: {$message}");
                return 1;
            }

            $modelPath = $modelResult['modelPath'];
            $this->info("✓ Model created: {$modelPath}");

            // Step 2: Modify Migration
            $currentStep++;
            $this->info("Step {$currentStep}/{$totalSteps}: Modifying migration...");
            $stepId = uniqid();
            
            Artisan::call('builder:generate-migration', [
                'modelName' => $modelName,
                'fields' => $fieldsJson,
                'stepId' => $stepId,
                '--soft-deletes' => $softDeletes
            ]);
            
            $migrationResult = $this->readStepResult($stepId);
            
            if (!$migrationResult || !$migrationResult['success']) {
                $message = $migrationResult['message'] ?? 'Migration modification failed';
                $this->error("Migration modification failed: {$message}");
                $this->writeResult($resultFile, 'error', "Migration modification failed: {$message}");
                return 1;
            }

            $migrationPath = $migrationResult['migrationPath'];
            $this->info("✓ Migration modified: {$migrationPath}");

            // Step 3: Run Migration
            $currentStep++;
            $this->info("Step {$currentStep}/{$totalSteps}: Running migration...");
            $migrateResult = $this->runMigration();
            
            if (!$migrateResult['success']) {
                $this->error("Migration failed: {$migrateResult['message']}");
                $this->writeResult($resultFile, 'error', "Migration failed: {$migrateResult['message']}");
                return 1;
            }

            $this->info("✓ Migration executed successfully");

            // Step 4: Generate Filament Resource
            $currentStep++;
            $this->info("Step {$currentStep}/{$totalSteps}: Generating Filament resource...");
            $stepId = uniqid();
            
            $resourceCommand = [
                'modelName' => $modelName,
                'stepId' => $stepId,
                '--soft-deletes' => $softDeletes,
                '--fields' => $fieldsJson, // Pass fields for view page generation
            ];

            // Add --view flag if view generation is enabled
            if ($generateView) {
                $resourceCommand['--view'] = true;
            }

            Artisan::call('builder:generate-resource', $resourceCommand);
            
            $resourceResult = $this->readStepResult($stepId);
            
            if (!$resourceResult || !$resourceResult['success']) {
                $message = $resourceResult['message'] ?? 'Resource generation failed';
                $this->error("Resource generation failed: {$message}");
                $this->writeResult($resultFile, 'error', "Resource generation failed: {$message}");
                return 1;
            }

            $resourcePath = $resourceResult['resourcePath'];
            $this->info("✓ Filament resource created: {$resourcePath}");

            // Check if view page was generated
            if ($generateView && isset($resourceResult['viewPagePath'])) {
                $this->info("✓ View page created: {$resourceResult['viewPagePath']}");
            }

            // Initialize result data
            $resultData = [
                'model' => $modelPath,
                'migration' => $migrationPath,
                'resource' => $resourcePath
            ];

            if ($generateView && isset($resourceResult['viewPagePath'])) {
                $resultData['viewPage'] = $resourceResult['viewPagePath'];
            }
            
            $successMessage = 'Model, migration, and Filament resource created successfully';

            if ($generateView) {
                $successMessage = 'Model, migration, Filament resource with view action created successfully';
            }

            // Step 5: Generate Factory and Seeder (Optional)
            if ($generateFactorySeeder) {
                $currentStep++;
                $this->info("Step {$currentStep}/{$totalSteps}: Generating Factory and Seeder...");
                $stepId = uniqid();
                
                Artisan::call('builder:generate-factory-seeder', [
                    'modelName' => $modelName,
                    'fields' => $fieldsJson,
                    'stepId' => $stepId,
                    '--count' => 10 // Generate 10 fake records by default
                ]);
                
                $factorySeederResult = $this->readStepResult($stepId);
                
                if (!$factorySeederResult || !$factorySeederResult['success']) {
                    $message = $factorySeederResult['message'] ?? 'Factory/Seeder generation failed';
                    $this->error("Factory/Seeder generation failed: {$message}");
                    $this->writeResult($resultFile, 'error', "Factory/Seeder generation failed: {$message}");
                    return 1;
                }

                $factoryPath = $factorySeederResult['factoryPath'];
                $seederPath = $factorySeederResult['seederPath'];
                $recordsCreated = $factorySeederResult['recordsCreated'] ?? 0;
                
                $this->info("✓ Factory created: {$factoryPath}");
                $this->info("✓ Seeder created: {$seederPath}");
                $this->info("✓ {$recordsCreated} records seeded successfully");
                
                // Add factory/seeder data to result
                $resultData['factory'] = $factoryPath;
                $resultData['seeder'] = $seederPath;
                $resultData['recordsSeeded'] = $recordsCreated;
                
                if ($generateView) {
                    $successMessage = 'Model, migration, Filament resource with view action, factory, and seeder created successfully';
                } else {
                    $successMessage = 'Model, migration, Filament resource, factory, and seeder created successfully';
                }
            }

            // All steps completed successfully
            $this->info("All steps completed successfully!");
            
            $this->writeResult($resultFile, 'success', $successMessage, $resultData);
            
            return 0;

        } catch (\Exception $e) {
            $this->error("Exception occurred: {$e->getMessage()}");
            $this->writeResult($resultFile, 'error', 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Read step result from temporary file
     */
    protected function readStepResult(string $stepId): ?array
    {
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        // Wait for the file to be created (max 5 seconds)
        $maxAttempts = 10;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            if (File::exists($stepResultFile)) {
                $result = json_decode(File::get($stepResultFile), true);
                @unlink($stepResultFile);
                return $result;
            }
            usleep(500000); // 0.5 seconds
            $attempt++;
        }
        
        return null;
    }

    /**
     * Run the database migration
     */
    protected function runMigration(): array
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            
            if ($exitCode === 0) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => 'Migration command failed with exit code: ' . $exitCode];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Write the final result to the result file
     */
    protected function writeResult(string $filePath, string $status, string $message, array $additionalData = []): void
    {
        $result = array_merge([
            'status' => $status,
            'message' => $message
        ], $additionalData);
        
        File::put($filePath, json_encode($result));
    }
}