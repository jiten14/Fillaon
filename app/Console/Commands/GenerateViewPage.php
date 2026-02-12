<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateViewPage extends Command
{
    protected $signature = 'builder:generate-view-page {modelName} {fields} {stepId}';
    protected $description = 'Modify the View page with a professional infolist';

    public function handle()
    {
        $modelName = $this->argument('modelName');
        $fieldsJson = $this->argument('fields');
        $stepId = $this->argument('stepId');
        
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        try {
            $fields = json_decode($fieldsJson, true);
            
            if (!$fields || !is_array($fields)) {
                $this->writeStepResult($stepResultFile, false, 'Invalid fields format');
                return 1;
            }

            $viewPagePath = app_path('Filament/Resources/' . $modelName . 'Resource/Pages/View' . $modelName . '.php');
            
            if (!File::exists($viewPagePath)) {
                $this->writeStepResult($stepResultFile, false, 'View page file does not exist');
                return 1;
            }

            // Modify the View page
            $modified = $this->modifyViewPage($viewPagePath, $fields);
            
            if (!$modified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify view page');
                return 1;
            }

            $this->writeStepResult($stepResultFile, true, 'View page modified successfully', [
                'viewPagePath' => $viewPagePath
            ]);
            
            return 0;

        } catch (\Exception $e) {
            $this->writeStepResult($stepResultFile, false, 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Modify the View page to add a professional infolist
     */
    protected function modifyViewPage(string $viewPagePath, array $fields): bool
    {
        try {
            $content = File::get($viewPagePath);
            
            // Add Infolist imports if not present
            if (strpos($content, 'use Filament\Infolists\Infolist;') === false) {
                $content = str_replace(
                    'use Filament\Resources\Pages\ViewRecord;',
                    "use Filament\Resources\Pages\ViewRecord;\nuse Filament\Infolists\Infolist;\nuse Filament\Infolists\Components\TextEntry;\nuse Filament\Infolists\Components\Section;\nuse Filament\Infolists\Components\Grid;",
                    $content
                );
            }

            // Generate infolist schema based on fields
            $infolistSchema = $this->generateInfolistSchema($fields);
            
            // Add infolist method
            $infolistMethod = <<<PHP

    public function infolist(Infolist \$infolist): Infolist
    {
        return \$infolist
            ->schema([
{$infolistSchema}
            ]);
    }
PHP;

            // Add the infolist method before the closing brace
            $content = preg_replace(
                '/\n}\s*$/',
                "{$infolistMethod}\n}",
                $content
            );

            File::put($viewPagePath, $content);
            return true;
            
        } catch (\Exception $e) {
            $this->error('Exception in modifyViewPage: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate infolist schema based on fields
     */
    protected function generateInfolistSchema(array $fields): string
    {
        // Group fields into logical sections
        $basicFields = [];
        $textFields = [];
        $numericFields = [];
        $dateFields = [];
        $jsonFields = [];
        $booleanFields = [];
        
        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'string';
            
            // Skip timestamps as they'll be in metadata section
            if (in_array($name, ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            switch ($type) {
                case 'text':
                case 'longText':
                case 'mediumText':
                    $textFields[] = $field;
                    break;
                    
                case 'integer':
                case 'bigInteger':
                case 'tinyInteger':
                case 'smallInteger':
                case 'decimal':
                case 'float':
                case 'double':
                    $numericFields[] = $field;
                    break;
                    
                case 'date':
                case 'datetime':
                case 'timestamp':
                    $dateFields[] = $field;
                    break;
                    
                case 'json':
                case 'jsonb':
                    $jsonFields[] = $field;
                    break;
                    
                case 'boolean':
                    $booleanFields[] = $field;
                    break;
                    
                default:
                    $basicFields[] = $field;
                    break;
            }
        }
        
        $sections = [];
        
        // Basic Information Section
        if (!empty($basicFields) || !empty($booleanFields)) {
            $entries = [];
            
            foreach ($basicFields as $field) {
                $name = $field['name'];
                $label = $this->generateLabel($name);
                $nullable = $field['nullable'] ?? false;
                
                $entry = "                        TextEntry::make('{$name}')\n";
                $entry .= "                            ->label('{$label}')\n";
                $entry .= "                            ->copyable()\n";
                
                if ($nullable) {
                    $entry .= "                            ->placeholder('N/A')\n";
                }
                
                $entry .= "                            ->icon('heroicon-m-document-text'),";
                $entries[] = $entry;
            }
            
            foreach ($booleanFields as $field) {
                $name = $field['name'];
                $label = $this->generateLabel($name);
                
                $entry = "                        TextEntry::make('{$name}')\n";
                $entry .= "                            ->label('{$label}')\n";
                $entry .= "                            ->badge()\n";
                $entry .= "                            ->color(fn (\$state): string => \$state ? 'success' : 'danger')\n";
                $entry .= "                            ->formatStateUsing(fn (\$state): string => \$state ? 'Yes' : 'No')\n";
                $entry .= "                            ->icon(fn (\$state): string => \$state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),";
                $entries[] = $entry;
            }
            
            $entriesStr = implode("\n", $entries);
            $sections[] = <<<PHP
                Section::make('Basic Information')
                    ->icon('heroicon-o-information-circle')
                    ->description('Core details and information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
{$entriesStr}
                            ]),
                    ])
                    ->collapsible(),
PHP;
        }
        
        // Text Content Section
        if (!empty($textFields)) {
            $entries = [];
            
            foreach ($textFields as $field) {
                $name = $field['name'];
                $label = $this->generateLabel($name);
                $nullable = $field['nullable'] ?? false;
                
                $entry = "                        TextEntry::make('{$name}')\n";
                $entry .= "                            ->label('{$label}')\n";
                $entry .= "                            ->markdown()\n";
                $entry .= "                            ->prose()\n";
                $entry .= "                            ->columnSpanFull()\n";
                
                if ($nullable) {
                    $entry .= "                            ->placeholder('No content available')\n";
                }
                
                $entry .= "                            ->icon('heroicon-m-document-text'),";
                $entries[] = $entry;
            }
            
            $entriesStr = implode("\n", $entries);
            $sections[] = <<<PHP
                Section::make('Content')
                    ->icon('heroicon-o-document-text')
                    ->description('Text and content fields')
                    ->schema([
{$entriesStr}
                    ])
                    ->collapsible(),
PHP;
        }
        
        // Numeric Data Section
        if (!empty($numericFields)) {
            $entries = [];
            
            foreach ($numericFields as $field) {
                $name = $field['name'];
                $label = $this->generateLabel($name);
                $type = $field['type'];
                $nullable = $field['nullable'] ?? false;
                
                $entry = "                        TextEntry::make('{$name}')\n";
                $entry .= "                            ->label('{$label}')\n";
                
                if (in_array($type, ['decimal', 'float', 'double'])) {
                    $entry .= "                            ->numeric(2)\n";
                    $entry .= "                            ->badge()\n";
                    $entry .= "                            ->color('info')\n";
                } else {
                    $entry .= "                            ->numeric()\n";
                }
                
                if ($nullable) {
                    $entry .= "                            ->placeholder('0')\n";
                }
                
                $entry .= "                            ->icon('heroicon-m-hashtag'),";
                $entries[] = $entry;
            }
            
            $entriesStr = implode("\n", $entries);
            $sections[] = <<<PHP
                Section::make('Numeric Data')
                    ->icon('heroicon-o-calculator')
                    ->description('Numerical values and metrics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
{$entriesStr}
                            ]),
                    ])
                    ->collapsible(),
PHP;
        }
        
        // Dates Section
        if (!empty($dateFields)) {
            $entries = [];
            
            foreach ($dateFields as $field) {
                $name = $field['name'];
                $label = $this->generateLabel($name);
                $type = $field['type'];
                $nullable = $field['nullable'] ?? false;
                
                $entry = "                        TextEntry::make('{$name}')\n";
                $entry .= "                            ->label('{$label}')\n";
                
                if ($type === 'date') {
                    $entry .= "                            ->date('M d, Y')\n";
                } else {
                    $entry .= "                            ->dateTime('M d, Y H:i')\n";
                }
                
                $entry .= "                            ->badge()\n";
                $entry .= "                            ->color('primary')\n";
                
                if ($nullable) {
                    $entry .= "                            ->placeholder('Not set')\n";
                }
                
                $entry .= "                            ->icon('heroicon-m-calendar'),";
                $entries[] = $entry;
            }
            
            $entriesStr = implode("\n", $entries);
            $sections[] = <<<PHP
                Section::make('Dates & Times')
                    ->icon('heroicon-o-calendar')
                    ->description('Date and time information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
{$entriesStr}
                            ]),
                    ])
                    ->collapsible(),
PHP;
        }
        
        // JSON Data Section
        if (!empty($jsonFields)) {
            $entries = [];
            
            foreach ($jsonFields as $field) {
                $name = $field['name'];
                $label = $this->generateLabel($name);
                $nullable = $field['nullable'] ?? false;
                
                $entry = "                        TextEntry::make('{$name}')\n";
                $entry .= "                            ->label('{$label}')\n";
                $entry .= "                            ->formatStateUsing(fn (\$state): string => \$state ? json_encode(\$state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'N/A')\n";
                $entry .= "                            ->columnSpanFull()\n";
                $entry .= "                            ->copyable()\n";
                
                if ($nullable) {
                    $entry .= "                            ->placeholder('No data')\n";
                }
                
                $entry .= "                            ->icon('heroicon-m-code-bracket'),";
                $entries[] = $entry;
            }
            
            $entriesStr = implode("\n", $entries);
            $sections[] = <<<PHP
                Section::make('JSON Data')
                    ->icon('heroicon-o-code-bracket')
                    ->description('Structured data and JSON fields')
                    ->schema([
{$entriesStr}
                    ])
                    ->collapsible()
                    ->collapsed(),
PHP;
        }
        
        // Metadata Section (always present with timestamps)
        $sections[] = <<<PHP
                Section::make('Metadata')
                    ->icon('heroicon-o-clock')
                    ->description('Record tracking information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('M d, Y H:i')
                                    ->badge()
                                    ->icon('heroicon-m-plus-circle')
                                    ->color('success'),
                                TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime('M d, Y H:i')
                                    ->badge()
                                    ->since()
                                    ->icon('heroicon-m-arrow-path')
                                    ->color('warning'),
                                TextEntry::make('deleted_at')
                                    ->label('Deleted At')
                                    ->dateTime('M d, Y H:i')
                                    ->badge()
                                    ->icon('heroicon-m-trash')
                                    ->color('danger')
                                    ->placeholder('Active')
                                    ->visible(fn (\$record) => \$record->deleted_at !== null),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
PHP;
        
        return implode("\n", $sections);
    }

    /**
     * Generate a human-readable label from field name
     */
    protected function generateLabel(string $fieldName): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $fieldName));
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