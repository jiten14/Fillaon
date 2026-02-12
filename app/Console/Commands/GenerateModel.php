<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class GenerateModel extends Command
{
    protected $signature = 'builder:generate-model {modelName} {fields} {stepId} {--soft-deletes}';
    protected $description = 'Generate model and modify it with fillable and casts properties';

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

            // Generate Model and Migration using Laravel's artisan command
            Artisan::call('make:model', [
                'name' => $modelName,
                '-m' => true // This creates migration as well
            ]);

            $modelPath = app_path('Models/' . $modelName . '.php');
            
            if (!File::exists($modelPath)) {
                $this->writeStepResult($stepResultFile, false, 'Model file was not created');
                return 1;
            }

            // Modify the Model file
            $modelModified = $this->modifyModel($modelPath, $fields, $softDeletes);
            
            if (!$modelModified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify model file');
                return 1;
            }

            // Success
            $this->writeStepResult($stepResultFile, true, 'Model created and modified successfully', [
                'modelPath' => $modelPath
            ]);
            
            return 0;

        } catch (\Exception $e) {
            $this->writeStepResult($stepResultFile, false, 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Modify the model file to add fillable, casts, and soft deletes
     */
    protected function modifyModel(string $modelPath, array $fields, bool $softDeletes): bool
    {
        try {
            $content = File::get($modelPath);
            
            // Build fillable array
            $fillableFields = [];
            foreach ($fields as $field) {
                $fillableFields[] = "        '" . $field['name'] . "'";
            }
            $fillableString = implode(",\n", $fillableFields);
            
            // Build casts array
            $casts = [];
            foreach ($fields as $field) {
                $castType = $this->getCastType($field['type']);
                if ($castType) {
                    $casts[] = "        '" . $field['name'] . "' => '" . $castType . "'";
                }
            }
            $castsString = implode(",\n", $casts);
            
            // Add use statements if soft deletes
            $useStatements = "use HasFactory;";
            if ($softDeletes) {
                $useStatements = "use HasFactory, SoftDeletes;";
                
                // Add SoftDeletes import after namespace
                if (!str_contains($content, 'use Illuminate\Database\Eloquent\SoftDeletes;')) {
                    $content = str_replace(
                        "use Illuminate\Database\Eloquent\Model;",
                        "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\SoftDeletes;",
                        $content
                    );
                }
            }
            
            // Replace the use statement in class
            $content = preg_replace(
                '/use HasFactory;/',
                $useStatements,
                $content
            );
            
            // Add fillable and casts properties
            $properties = "    protected \$fillable = [\n{$fillableString}\n    ];\n\n";
            
            if (!empty($casts)) {
                $properties .= "    protected \$casts = [\n{$castsString}\n    ];\n";
            }
            
            // Insert after "use HasFactory" line
            $content = preg_replace(
                '/(use\s+HasFactory[^;]*;)/',
                "$1\n\n{$properties}",
                $content
            );
            
            File::put($modelPath, $content);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the appropriate cast type for a field type
     */
    protected function getCastType(string $type): ?string
    {
        return match($type) {
            'boolean' => 'boolean',
            'integer', 'bigInteger', 'tinyInteger', 'smallInteger' => 'integer',
            'decimal', 'double', 'float' => 'decimal:2',
            'json', 'jsonb' => 'array',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            default => null
        };
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