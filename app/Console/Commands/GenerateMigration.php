<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateMigration extends Command
{
    protected $signature = 'builder:generate-migration {modelName} {fields} {stepId} {--soft-deletes}';
    protected $description = 'Modify migration file with field definitions';

    public function handle()
    {
        $modelName = $this->argument('modelName');
        $fieldsJson = $this->argument('fields');
        $stepId = $this->argument('stepId');
        $softDeletes = $this->option('soft-deletes');
        
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        try {
            $fields = json_decode($fieldsJson, true);
            
            if (!$fields || !is_array($fields)) {
                $this->writeStepResult($stepResultFile, false, 'Invalid fields format');
                return 1;
            }

            // Find the migration file
            $tableName = Str::plural(Str::snake($modelName));
            $migrationPath = $this->findLatestMigration($tableName);
            
            if (!$migrationPath) {
                $this->writeStepResult($stepResultFile, false, 'Migration file not found');
                return 1;
            }

            // Modify the Migration file
            $migrationModified = $this->modifyMigration($migrationPath, $fields, $softDeletes);
            
            if (!$migrationModified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify migration file');
                return 1;
            }

            // Success
            $this->writeStepResult($stepResultFile, true, 'Migration modified successfully', [
                'migrationPath' => $migrationPath
            ]);
            
            return 0;

        } catch (\Exception $e) {
            $this->writeStepResult($stepResultFile, false, 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Find the latest migration file for the given table name
     */
    protected function findLatestMigration(string $tableName): ?string
    {
        $migrationPath = database_path('migrations');
        $files = File::files($migrationPath);
        
        // Sort by modification time (latest first)
        usort($files, function($a, $b) {
            return $b->getMTime() - $a->getMTime();
        });
        
        // Find migration with the table name
        foreach ($files as $file) {
            if (str_contains($file->getFilename(), 'create_' . $tableName . '_table')) {
                return $file->getPathname();
            }
        }
        
        return null;
    }

    /**
     * Modify the migration file to add field definitions
     */
    protected function modifyMigration(string $migrationPath, array $fields, bool $softDeletes): bool
    {
        try {
            $content = File::get($migrationPath);
            
            // Build field definitions
            $fieldDefinitions = [];
            foreach ($fields as $field) {
                $line = "            \$table->" . $field['type'] . "('" . $field['name'] . "')";
                
                if (!empty($field['nullable'])) {
                    $line .= "->nullable()";
                }
                
                if (!empty($field['unique'])) {
                    $line .= "->unique()";
                }
                
                if (isset($field['default']) && $field['default'] !== '' && $field['default'] !== null) {
                    if ($field['type'] === 'boolean') {
                        $line .= "->default(" . ($field['default'] ? 'true' : 'false') . ")";
                    } elseif (is_numeric($field['default'])) {
                        $line .= "->default(" . $field['default'] . ")";
                    } else {
                        $line .= "->default('" . $field['default'] . "')";
                    }
                }
                
                $line .= ";";
                $fieldDefinitions[] = $line;
            }
            
            // Add soft deletes if enabled
            if ($softDeletes) {
                $fieldDefinitions[] = "            \$table->softDeletes();";
            }
            
            $fieldsString = implode("\n", $fieldDefinitions);
            
            // Find and replace the Schema::create block
            $pattern = '/(\$table->id\(\);)(.*?)(\$table->timestamps\(\);)/s';
            $replacement = "$1\n{$fieldsString}\n            $3";
            
            $content = preg_replace($pattern, $replacement, $content);
            
            File::put($migrationPath, $content);
            return true;
            
        } catch (\Exception $e) {
            return false;
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