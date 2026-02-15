<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateFactorySeeder extends Command
{
    protected $signature = 'builder:generate-factory-seeder {modelName} {fields} {stepId} {--count=10}';
    protected $description = 'Generate Factory and Seeder for the model with fake data';

    public function handle()
    {
        $modelName = $this->argument('modelName');
        $fieldsJson = $this->argument('fields');
        $stepId = $this->argument('stepId');
        $count = $this->option('count');
        
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        try {
            $fields = json_decode($fieldsJson, true);
            
            if (!$fields || !is_array($fields)) {
                $this->writeStepResult($stepResultFile, false, 'Invalid fields format');
                return 1;
            }

            // Step 1: Generate Factory
            $this->info("Generating factory for {$modelName}...");
            $exitCode = Artisan::call('make:factory', [
                'name' => $modelName . 'Factory',
                '--model' => $modelName
            ]);
            
            if ($exitCode !== 0) {
                $this->writeStepResult($stepResultFile, false, 'Failed to generate factory');
                return 1;
            }

            $factoryPath = database_path('factories/' . $modelName . 'Factory.php');
            
            if (!File::exists($factoryPath)) {
                $this->writeStepResult($stepResultFile, false, 'Factory file was not created');
                return 1;
            }

            // Step 2: Modify Factory with field definitions
            $factoryModified = $this->modifyFactory($factoryPath, $fields);
            
            if (!$factoryModified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify factory');
                return 1;
            }

            $this->info("Factory modified successfully");

            // Step 3: Generate Seeder
            $this->info("Generating seeder for {$modelName}...");
            $exitCode = Artisan::call('make:seeder', [
                'name' => $modelName . 'Seeder'
            ]);
            
            if ($exitCode !== 0) {
                $this->writeStepResult($stepResultFile, false, 'Failed to generate seeder');
                return 1;
            }

            $seederPath = database_path('seeders/' . $modelName . 'Seeder.php');
            
            if (!File::exists($seederPath)) {
                $this->writeStepResult($stepResultFile, false, 'Seeder file was not created');
                return 1;
            }

            // Step 4: Modify Seeder to use factory
            $seederModified = $this->modifySeeder($seederPath, $modelName, $count);
            
            if (!$seederModified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify seeder');
                return 1;
            }

            $this->info("Seeder modified successfully");

            // Step 5: Run the seeder to populate data
            $this->info("Running seeder to populate data...");
            $seedResult = $this->runSeeder($modelName);
            
            if (!$seedResult['success']) {
                $this->writeStepResult($stepResultFile, false, 'Seeder execution failed: ' . $seedResult['message']);
                return 1;
            }

            $this->info("Data seeded successfully");

            // Success
            $this->writeStepResult($stepResultFile, true, 'Factory and Seeder generated and executed successfully', [
                'factoryPath' => $factoryPath,
                'seederPath' => $seederPath,
                'recordsCreated' => $count
            ]);
            
            return 0;

        } catch (\Exception $e) {
            $this->writeStepResult($stepResultFile, false, 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Modify the factory file to include field definitions with fake data
     */
    protected function modifyFactory(string $factoryPath, array $fields): bool
    {
        try {
            $content = File::get($factoryPath);
            
            // Build the field definitions array
            $fieldDefinitions = [];
            
            foreach ($fields as $field) {
                $fieldName = $field['name'];
                $fieldType = $field['type'];
                $nullable = $field['nullable'] ?? false;
                $default = $field['default'] ?? null;
                
                // Generate appropriate faker method based on field type
                $fakerMethod = $this->getFakerMethod($fieldName, $fieldType, $nullable, $default);
                
                $fieldDefinitions[] = "            '{$fieldName}' => {$fakerMethod},";
            }
            
            $fieldDefinitionsString = implode("\n", $fieldDefinitions);
            
            // Replace the return statement in the definition method
            $pattern = '/(public function definition\(\):\s*array\s*\{)\s*return\s*\[(.*?)\];/s';
            
            $replacement = "$1\n        return [\n{$fieldDefinitionsString}\n        ];";
            
            $content = preg_replace($pattern, $replacement, $content);

            File::put($factoryPath, $content);
            return true;
            
        } catch (\Exception $e) {
            $this->error('Exception in modifyFactory: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get appropriate Faker method based on field name and type
     */
    protected function getFakerMethod(string $fieldName, string $fieldType, bool $nullable, $default): string
    {
        $fieldNameLower = strtolower($fieldName);
        
        // Handle nullable fields
        $optionalWrapper = $nullable ? 'fake()->optional()->' : 'fake()->';
        
        // Handle default values
        if ($default !== null && $default !== '') {
            return "'{$default}'";
        }
        
        // Field name-based faker methods (more specific)
        if (str_contains($fieldNameLower, 'email')) {
            return "{$optionalWrapper}unique()->safeEmail()";
        }
        if (str_contains($fieldNameLower, 'phone') || str_contains($fieldNameLower, 'mobile') || str_contains($fieldNameLower, 'tel') || str_contains($fieldNameLower, 'contact')) {
            return "{$optionalWrapper}phoneNumber()";
        }
        if (str_contains($fieldNameLower, 'address')) {
            return "{$optionalWrapper}address()";
        }
        if (str_contains($fieldNameLower, 'city')) {
            return "{$optionalWrapper}city()";
        }
        if (str_contains($fieldNameLower, 'country')) {
            return "{$optionalWrapper}country()";
        }
        if (str_contains($fieldNameLower, 'state')) {
            return "{$optionalWrapper}state()";
        }
        if (str_contains($fieldNameLower, 'zip') || str_contains($fieldNameLower, 'postal')) {
            return "{$optionalWrapper}postcode()";
        }
        if (str_contains($fieldNameLower, 'company')) {
            return "{$optionalWrapper}company()";
        }
        if (str_contains($fieldNameLower, 'name') && !str_contains($fieldNameLower, 'user') && !str_contains($fieldNameLower, 'file')) {
            if (str_contains($fieldNameLower, 'first')) {
                return "{$optionalWrapper}firstName()";
            }
            if (str_contains($fieldNameLower, 'last')) {
                return "{$optionalWrapper}lastName()";
            }
            return "{$optionalWrapper}name()";
        }
        if (str_contains($fieldNameLower, 'title')) {
            // For string type titles, use text(100) to ensure it fits
            if ($fieldType === 'string') {
                return "{$optionalWrapper}text(100)";
            }
            return "{$optionalWrapper}sentence(3)";
        }
        if (str_contains($fieldNameLower, 'slug')) {
            return "{$optionalWrapper}slug()";
        }
        if (str_contains($fieldNameLower, 'url') || str_contains($fieldNameLower, 'website')) {
            return "{$optionalWrapper}url()";
        }
        if (str_contains($fieldNameLower, 'image') || str_contains($fieldNameLower, 'photo') || str_contains($fieldNameLower, 'avatar')) {
            return "{$optionalWrapper}imageUrl(640, 480)";
        }
        if (str_contains($fieldNameLower, 'description') || str_contains($fieldNameLower, 'bio')) {
            // If it's a string type (max 255 chars), use text(200) instead of paragraph()
            if ($fieldType === 'string') {
                return "{$optionalWrapper}text(200)";
            }
            return "{$optionalWrapper}paragraph()";
        }
        if (str_contains($fieldNameLower, 'content') || str_contains($fieldNameLower, 'body')) {
            // If it's a string type (max 255 chars), use text(200) instead of paragraphs()
            if ($fieldType === 'string') {
                return "{$optionalWrapper}text(200)";
            }
            return "{$optionalWrapper}paragraphs(3, true)";
        }
        if (str_contains($fieldNameLower, 'note') || str_contains($fieldNameLower, 'comment') || str_contains($fieldNameLower, 'remark')) {
            // For notes, comments, remarks - if string type, use text(200)
            if ($fieldType === 'string') {
                return "{$optionalWrapper}text(200)";
            }
            return "{$optionalWrapper}paragraph()";
        }
        if (str_contains($fieldNameLower, 'summary')) {
            // For summaries - if string type, use text(200)
            if ($fieldType === 'string') {
                return "{$optionalWrapper}text(200)";
            }
            return "{$optionalWrapper}paragraph()";
        }
        if (str_contains($fieldNameLower, 'color')) {
            return "{$optionalWrapper}hexColor()";
        }
        if (str_contains($fieldNameLower, 'price') || str_contains($fieldNameLower, 'amount')) {
            return "{$optionalWrapper}randomFloat(2, 10, 1000)";
        }
        if (str_contains($fieldNameLower, 'quantity') || str_contains($fieldNameLower, 'stock')) {
            return "{$optionalWrapper}numberBetween(0, 100)";
        }
        if (str_contains($fieldNameLower, 'rating')) {
            return "{$optionalWrapper}numberBetween(1, 5)";
        }
        if (str_contains($fieldNameLower, 'percentage') || str_contains($fieldNameLower, 'percent')) {
            return "{$optionalWrapper}numberBetween(0, 100)";
        }
        if (str_contains($fieldNameLower, 'age')) {
            return "{$optionalWrapper}numberBetween(18, 80)";
        }
        if (str_contains($fieldNameLower, 'year')) {
            return "{$optionalWrapper}year()";
        }
        if (str_contains($fieldNameLower, 'month')) {
            return "{$optionalWrapper}month()";
        }
        if (str_contains($fieldNameLower, 'day')) {
            return "{$optionalWrapper}dayOfMonth()";
        }
        if (str_contains($fieldNameLower, 'status')) {
            return "{$optionalWrapper}randomElement(['active', 'inactive', 'pending'])";
        }
        if (str_contains($fieldNameLower, 'type')) {
            return "{$optionalWrapper}randomElement(['type1', 'type2', 'type3'])";
        }
        if (str_contains($fieldNameLower, 'category')) {
            return "{$optionalWrapper}word()";
        }
        if (str_contains($fieldNameLower, 'tag')) {
            return "{$optionalWrapper}word()";
        }
        
        // Field type-based faker methods (fallback)
        switch ($fieldType) {
            case 'string':
                // For generic string fields, use text(200) to stay within 255 char limit
                // This is safer than sentence(3) which could vary in length
                return "{$optionalWrapper}text(200)";
            
            case 'text':
            case 'mediumText':
                return "{$optionalWrapper}paragraph()";
            
            case 'longText':
                return "{$optionalWrapper}paragraphs(3, true)";
            
            case 'integer':
            case 'bigInteger':
            case 'tinyInteger':
            case 'smallInteger':
                return "{$optionalWrapper}numberBetween(1, 100)";
            
            case 'boolean':
                return "{$optionalWrapper}boolean()";
            
            case 'date':
                return "{$optionalWrapper}date()";
            
            case 'datetime':
            case 'timestamp':
                return "{$optionalWrapper}dateTime()";
            
            case 'decimal':
            case 'float':
            case 'double':
                return "{$optionalWrapper}randomFloat(2, 0, 1000)";
            
            case 'json':
            case 'jsonb':
                return "{$optionalWrapper}json()";
            
            default:
                return "{$optionalWrapper}word()";
        }
    }

    /**
     * Modify the seeder file to use the factory
     */
    protected function modifySeeder(string $seederPath, string $modelName, int $count): bool
    {
        try {
            $content = File::get($seederPath);
            
            // Add model import
            $modelImport = "use App\\Models\\{$modelName};";
            
            // Check if import already exists
            if (!str_contains($content, $modelImport)) {
                $content = str_replace(
                    "use Illuminate\Database\Seeder;",
                    "use Illuminate\Database\Seeder;\n{$modelImport}",
                    $content
                );
            }
            
            // Build the seeder run method content
            $runMethodContent = <<<PHP

        {$modelName}::factory()
            ->count({$count})
            ->create();
PHP;
            
            // Replace the run method
            $pattern = '/(public function run\(\):\s*void\s*\{)(.*?)(\})/s';
            
            $replacement = "$1{$runMethodContent}\n    $3";
            
            $content = preg_replace($pattern, $replacement, $content);

            File::put($seederPath, $content);
            return true;
            
        } catch (\Exception $e) {
            $this->error('Exception in modifySeeder: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Run the seeder to populate the database
     */
    protected function runSeeder(string $modelName): array
    {
        try {
            $exitCode = Artisan::call('db:seed', [
                '--class' => $modelName . 'Seeder',
                '--force' => true
            ]);
            
            if ($exitCode === 0) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => 'Seeder command failed with exit code: ' . $exitCode];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Seeder execution failed: ' . $e->getMessage()];
        }
    }

    /**
     * Write step result to file
     */
    protected function writeStepResult(string $filePath, bool $success, string $message, array $additionalData = []): void
    {
        $result = array_merge([
            'success' => $success,
            'message' => $message
        ], $additionalData);
        
        File::put($filePath, json_encode($result));
    }
}