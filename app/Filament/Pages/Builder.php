<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;

class Builder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static string $view = 'filament.pages.builder';
    protected static ?string $navigationGroup = 'Builder';
    protected static ?string $navigationLabel = 'Resource Builder';
    protected static ?int $navigationSort = -1;

    protected static ?string $title = '';

    public ?array $data = [];

    /**
     * Check if user has Superadmin role
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('Superadmin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'modelName' => '',
            'fields' => [],
            'softDeletes' => false,
            'generateFactorySeeder' => false,
            'generateView' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Model Configuration')
                    ->description('Define your model name and configuration options')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('modelName')
                                    ->label('Model Name')
                                    ->required()
                                    ->placeholder('e.g., Post, Category, Product')
                                    ->helperText('Enter the model name in singular form (PascalCase)')
                                    ->prefixIcon('heroicon-o-cube-transparent')
                                    ->autocomplete(false)
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('softDeletes')
                                    ->label('Enable Soft Deletes')
                                    ->helperText('Add soft delete functionality to this model')
                                    ->default(false)
                                    ->inline(false)
                                    ->live()
                                    ->columnSpan(1),
                                    
                                Toggle::make('generateFactorySeeder')
                                    ->label('Generate Factory & Seeder')
                                    ->helperText('Automatically generate factory and seeder with 10 sample records')
                                    ->default(false)
                                    ->inline(false)
                                    ->live()
                                    ->columnSpan(1),
                            ]),
                        
                        Toggle::make('generateView')
                            ->label('Generate View Action')
                            ->helperText('Add view action to the resource table')
                            ->default(false)
                            ->inline(false)
                            ->live(),
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->collapsed(false),

                Section::make('Table Fields')
                    ->description('Add and configure fields for your database table')
                    ->icon('heroicon-o-table-cells')
                    ->schema([
                        Repeater::make('fields')
                            ->label('')
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Field Name')
                                            ->required()
                                            ->placeholder('e.g., title, description, price')
                                            ->prefixIcon('heroicon-o-hashtag')
                                            ->autocomplete(false)
                                            ->columnSpan(3),

                                        Select::make('type')
                                            ->label('Field Type')
                                            ->required()
                                            ->options([
                                                'string' => 'String (255)',
                                                'text' => 'Text',
                                                'longText' => 'Long Text',
                                                'mediumText' => 'Medium Text',
                                                'integer' => 'Integer',
                                                'bigInteger' => 'Big Integer',
                                                'tinyInteger' => 'Tiny Integer',
                                                'smallInteger' => 'Small Integer',
                                                'boolean' => 'Boolean',
                                                'date' => 'Date',
                                                'datetime' => 'DateTime',
                                                'timestamp' => 'Timestamp',
                                                'decimal' => 'Decimal',
                                                'float' => 'Float',
                                                'double' => 'Double',
                                                'json' => 'JSON',
                                                'jsonb' => 'JSONB (PostgreSQL)',
                                            ])
                                            ->default('string')
                                            ->searchable()
                                            ->prefixIcon('heroicon-o-variable')
                                            ->columnSpan(3),

                                        TextInput::make('default')
                                            ->label('Default Value')
                                            ->placeholder('Optional default value')
                                            ->prefixIcon('heroicon-o-sparkles')
                                            ->autocomplete(false)
                                            ->columnSpan(4),

                                        Toggle::make('nullable')
                                            ->label('Nullable')
                                            ->default(false)
                                            ->inline(false)
                                            ->columnSpan(1),

                                        Toggle::make('unique')
                                            ->label('Unique')
                                            ->default(false)
                                            ->inline(false)
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('Add New Field')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable()
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            )
                            ->itemLabel(fn (array $state): ?string => $state['name'] 
                                ? $state['name'] . ' (' . ($state['type'] ?? 'string') . ')' 
                                : 'New Field')
                            ->defaultItems(0)
                            ->minItems(1)
                            ->live(),
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->collapsed(false),
            ])
            ->statePath('data');
    }

    /**
     * Generate model, migration, and resource by calling the master command
     */
    public function generate()
    {
        $data = $this->form->getState();

        // Validate model name
        if (empty($data['modelName'])) {
            Notification::make()
                ->title('Error')
                ->body('Model name is required')
                ->danger()
                ->send();
            return;
        }

        // Validate fields
        if (empty($data['fields']) || count($data['fields']) === 0) {
            Notification::make()
                ->title('Error')
                ->body('At least one field is required')
                ->danger()
                ->send();
            return;
        }

        // Validate each field
        foreach ($data['fields'] as $field) {
            if (empty($field['name'])) {
                Notification::make()
                    ->title('Error')
                    ->body('All fields must have a name')
                    ->danger()
                    ->send();
                return;
            }
        }

        try {
            $fieldsJson = json_encode($data['fields']);
            $softDeletes = $data['softDeletes'] ?? false;
            $generateFactorySeeder = $data['generateFactorySeeder'] ?? false;
            $generateView = $data['generateView'] ?? false;

            // Create a unique identifier for this generation process
            $processId = uniqid();
            $resultFilePath = storage_path('app/builder_result_' . $processId . '.json');

            // Call the master command
            Artisan::call('builder:master', [
                'modelName' => $data['modelName'],
                'fields' => $fieldsJson,
                'processId' => $processId,
                '--soft-deletes' => $softDeletes,
                '--generate-factory-seeder' => $generateFactorySeeder,
                '--generate-view' => $generateView,
            ]);

            // Wait for the result file to be created (max 30 seconds)
            $result = $this->waitForResult($resultFilePath, 30);
            
            if ($result && isset($result['status']) && $result['status'] === 'success') {
                $this->handleSuccess($result);
            } else {
                $this->handleError($result);
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Generation failed: ' . $e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    /**
     * Wait for the result file to be created and return its contents
     */
    protected function waitForResult(string $resultFilePath, int $timeoutSeconds = 30): ?array
    {
        $maxAttempts = $timeoutSeconds * 2; // Check every 0.5 seconds
        $attempt = 0;
        $result = null;

        while ($attempt < $maxAttempts) {
            if (file_exists($resultFilePath)) {
                $resultJson = file_get_contents($resultFilePath);
                $result = json_decode($resultJson, true);
                
                // Clean up the result file
                @unlink($resultFilePath);
                break;
            }
            
            usleep(500000); // Sleep for 0.5 seconds
            $attempt++;
        }

        // Check if we timed out
        if ($result === null && $attempt >= $maxAttempts) {
            return [
                'status' => 'error',
                'message' => 'Operation timed out after ' . $timeoutSeconds . ' seconds.'
            ];
        }

        return $result;
    }

    /**
     * Handle successful generation
     */
    protected function handleSuccess(array $result): void
    {
        // Reset form
        $this->form->fill([
            'modelName' => '',
            'fields' => [],
            'softDeletes' => false,
            'generateFactorySeeder' => false,
            'generateView' => false,
        ]);

        // Show success notification
        $message = 'Your model, migration, and resource have been generated successfully.';
        
        if (isset($result['recordsSeeded']) && $result['recordsSeeded'] > 0) {
            $message = 'Your model, migration, resource, factory, and seeder have been generated successfully. ' . $result['recordsSeeded'] . ' sample records created.';
        }
        
        $message .= ' Redirecting to dashboard...';
        
        Notification::make()
            ->title('Success!')
            ->body($message)
            ->success()
            ->duration(3000)
            ->send();

        // Dispatch redirect event
        $this->dispatch('redirect-to-dashboard');
    }

    /**
     * Handle generation error
     */
    protected function handleError(?array $result): void
    {
        $errorMessage = 'Generation failed. ';
        
        if ($result && isset($result['message'])) {
            $errorMessage .= $result['message'];
        } else {
            $errorMessage .= 'Unknown error occurred.';
        }
        
        Notification::make()
            ->title('Generation Failed')
            ->body($errorMessage)
            ->danger()
            ->duration(8000)
            ->send();
    }
}