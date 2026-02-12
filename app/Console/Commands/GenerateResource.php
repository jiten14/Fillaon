<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateResource extends Command
{
    protected $signature = 'builder:generate-resource {modelName} {stepId} {--soft-deletes} {--view} {--fields=}';
    protected $description = 'Generate Filament resource for the model and customize it';

    public function handle()
    {
        $modelName = $this->argument('modelName');
        $stepId = $this->argument('stepId');
        $softDeletes = $this->option('soft-deletes');
        $generateView = $this->option('view');
        $fieldsJson = $this->option('fields'); // Add fields option
        
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        try {
            // Generate Filament Resource using Laravel's artisan command
            $command = [
                'name' => $modelName,
                '--generate' => true
            ];

            // Add soft-deletes flag if enabled
            if ($softDeletes) {
                $command['--soft-deletes'] = true;
            }

            // Add view flag if enabled
            if ($generateView) {
                $command['--view'] = true;
            }

            $exitCode = Artisan::call('make:filament-resource', $command);
            
            if ($exitCode !== 0) {
                $this->writeStepResult($stepResultFile, false, 'Failed to generate Filament resource');
                return 1;
            }

            $resourcePath = app_path('Filament/Resources/' . $modelName . 'Resource.php');
            
            if (!File::exists($resourcePath)) {
                $this->writeStepResult($stepResultFile, false, 'Resource file was not created');
                return 1;
            }

            // Always modify the Resource file for navigation settings
            $resourceModified = $this->modifyResourceNavigation($resourcePath, $modelName);
            
            if (!$resourceModified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify resource navigation');
                return 1;
            }

            // Add table actions - DeleteAction for normal, or Delete/ForceDelete/Restore for soft deletes
            // NOTE: We do NOT add ViewAction here because when --view flag is used,
            // Filament automatically adds ViewAction to the table
            $this->addTableActions($resourcePath, $softDeletes);

            // Modify Create page
            $createPagePath = app_path('Filament/Resources/' . $modelName . 'Resource/Pages/Create' . $modelName . '.php');
            if (File::exists($createPagePath)) {
                $this->modifyCreatePage($createPagePath);
            }

            // Modify Edit page
            $editPagePath = app_path('Filament/Resources/' . $modelName . 'Resource/Pages/Edit' . $modelName . '.php');
            if (File::exists($editPagePath)) {
                $this->modifyEditPage($editPagePath);
            }

            // Check if View page exists (only if --view flag was used)
            $viewPagePath = app_path('Filament/Resources/' . $modelName . 'Resource/Pages/View' . $modelName . '.php');
            
            // If view page was generated and fields are provided, modify it with professional infolist
            if ($generateView && File::exists($viewPagePath) && $fieldsJson) {
                $this->info('Generating professional infolist for view page...');
                $viewStepId = uniqid();
                
                Artisan::call('builder:generate-view-page', [
                    'modelName' => $modelName,
                    'fields' => $fieldsJson,
                    'stepId' => $viewStepId
                ]);
                
                $viewResult = $this->readStepResult($viewStepId);
                
                if ($viewResult && $viewResult['success']) {
                    $this->info('✓ View page infolist generated successfully');
                } else {
                    $this->warn('View page was created but infolist generation had issues');
                }
            }
            
            // Success
            $resultData = [
                'resourcePath' => $resourcePath,
                'createPagePath' => $createPagePath,
                'editPagePath' => $editPagePath
            ];

            if ($generateView && File::exists($viewPagePath)) {
                $resultData['viewPagePath'] = $viewPagePath;
            }

            $this->writeStepResult($stepResultFile, true, 'Filament resource generated and modified successfully', $resultData);
            
            return 0;

        } catch (\Exception $e) {
            $this->writeStepResult($stepResultFile, false, 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Modify the navigation properties of the generated Filament resource
     * Automatically generates navigationGroup, navigationSort, and navigationIcon based on model name
     */
    protected function modifyResourceNavigation(string $resourcePath, string $modelName): bool
    {
        try {
            $content = File::get($resourcePath);
            
            // Build the navigation properties automatically
            // navigationGroup = ModelName (e.g., 'User', 'Post', 'Product')
            // navigationSort = 1 (default)
            // navigationIcon = 'heroicon-s-arrow-path' (default)
            $navigationProperties = "    protected static ?string \$navigationGroup = '{$modelName}';\n\n";
            $navigationProperties .= "    protected static ?int \$navigationSort = 1;\n\n";
            $navigationProperties .= "    protected static ?string \$navigationIcon = 'heroicon-s-arrow-path';";
            
            // Replace the default navigationIcon line with our custom properties
            // Using exact string replacement like in AdvanceFillament.php
            $content = str_replace(
                "    protected static ?string \$navigationIcon = 'heroicon-o-rectangle-stack';",
                $navigationProperties,
                $content
            );

            File::put($resourcePath, $content);
            $this->info('Navigation properties successfully updated');
            return true;
            
        } catch (\Exception $e) {
            $this->error('Exception in modifyResourceNavigation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add table actions
     * - If soft deletes: Add DeleteAction, ForceDeleteAction, and RestoreAction
     * - If not soft deletes: Add only DeleteAction
     * 
     * NOTE: ViewAction is NOT added here because when --view flag is used in
     * make:filament-resource, Filament automatically adds ViewAction to the table
     */
    protected function addTableActions(string $resourcePath, bool $softDeletes): bool
    {
        try {
            $content = File::get($resourcePath);
            
            // Build the actions string
            $actions = "                Tables\Actions\EditAction::make(),";
            
            // Add delete-related actions
            if ($softDeletes) {
                // Add all soft delete actions
                $actions .= "\n                Tables\Actions\DeleteAction::make(),";
                $actions .= "\n                Tables\Actions\ForceDeleteAction::make(),";
                $actions .= "\n                Tables\Actions\RestoreAction::make(),";
            } else {
                // Add only DeleteAction
                $actions .= "\n                Tables\Actions\DeleteAction::make(),";
            }
            
            // Replace the EditAction line with all our actions
            $content = str_replace(
                "                Tables\Actions\EditAction::make(),",
                $actions,
                $content
            );

            File::put($resourcePath, $content);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Modify Create page to add customizations
     */
    protected function modifyCreatePage(string $createPagePath): bool
    {
        try {
            $content = File::get($createPagePath);
            
            // Add canCreateAnother property
            $property = "\n    protected static bool \$canCreateAnother = false;\n";
            $content = preg_replace(
                '/(protected static string \$resource[^;]+;)/',
                "$1{$property}",
                $content
            );

            // Add getRedirectUrl method
            $redirectMethod = <<<'PHP'

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
PHP;

            // Add getCreatedNotificationTitle method
            $notificationMethod = <<<'PHP'

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Record created successfully';
    }
PHP;

            // Add methods before the closing brace
            $content = preg_replace(
                '/\n}\s*$/',
                "{$redirectMethod}{$notificationMethod}\n}",
                $content
            );

            File::put($createPagePath, $content);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Modify Edit page to add customizations
     */
    protected function modifyEditPage(string $editPagePath): bool
    {
        try {
            $content = File::get($editPagePath);
            
            // Add getRedirectUrl method
            $redirectMethod = <<<'PHP'

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
PHP;

            // Add getSavedNotificationTitle method
            $notificationMethod = <<<'PHP'

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Record updated successfully';
    }
PHP;

            // Add methods before getHeaderActions or before closing brace
            if (strpos($content, 'getHeaderActions') !== false) {
                $content = preg_replace(
                    '/(protected function getHeaderActions\(\):)/',
                    "{$redirectMethod}{$notificationMethod}\n\n    $1",
                    $content
                );
            } else {
                $content = preg_replace(
                    '/\n}\s*$/',
                    "{$redirectMethod}{$notificationMethod}\n}",
                    $content
                );
            }

            File::put($editPagePath, $content);
            return true;
            
        } catch (\Exception $e) {
            return false;
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