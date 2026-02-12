<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

class PolicyGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static string $view = 'filament.pages.policy-generator';
    protected static ?string $navigationGroup = 'Builder';
    protected static ?string $navigationLabel = 'Policy Generator';
    protected static ?int $navigationSort = 100;

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
            'rolePermissions' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Info Section - Show existing policies
                Section::make('Policy Overview')
                    ->description(function () {
                        $stats = $this->getPolicyStatistics();
                        $existingPolicies = $this->getExistingPolicies();
                        
                        if (empty($existingPolicies)) {
                            return 'No policies have been generated yet. Start by selecting a model below.';
                        }
                        
                        $policyList = implode(', ', array_map(fn($p) => $p . 'Policy', $existingPolicies));
                        return sprintf(
                            'Existing policies (%d): %s',
                            count($existingPolicies),
                            $policyList
                        );
                    })
                    ->icon('heroicon-o-information-circle')
                    ->schema([])
                    ->collapsible()
                    ->collapsed(true)
                    ->visible(fn () => !empty($this->getExistingPolicies())),

                Section::make('Model Selection')
                    ->description(function () {
                        $stats = $this->getPolicyStatistics();
                        if ($stats['total'] === 0) {
                            return 'No models found in app/Models directory';
                        }
                        return sprintf(
                            'Select a model to generate a policy • %d model(s) available • %d already have policies',
                            $stats['without_policy'],
                            $stats['with_policy']
                        );
                    })
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Select::make('modelName')
                            ->label('Model')
                            ->required()
                            ->options(fn () => $this->getAvailableModels())
                            ->searchable()
                            ->prefixIcon('heroicon-o-cube-transparent')
                            ->placeholder(
                                fn () => empty($this->getAvailableModels()) 
                                    ? 'No models available - all models already have policies'
                                    : 'Select a model'
                            )
                            ->native(false)
                            ->live()
                            ->helperText(
                                fn () => empty($this->getAvailableModels())
                                    ? 'All existing models already have policies generated. Models with existing policies are automatically excluded.'
                                    : 'Select the model for which you want to generate a policy. Models with existing policies are not shown.'
                            )
                            ->disabled(fn () => empty($this->getAvailableModels())),
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->collapsed(false),

                Section::make('Role-Based Permissions')
                    ->description('Configure permissions for each role')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Repeater::make('rolePermissions')
                            ->label('Role Permissions')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('role')
                                            ->label('Role')
                                            ->required()
                                            ->options($this->getAvailableRoles())
                                            ->searchable()
                                            ->prefixIcon('heroicon-o-user-group')
                                            ->placeholder('Select a role')
                                            ->columnSpan(1)
                                            ->native(false),

                                        CheckboxList::make('permissions')
                                            ->label('Permissions')
                                            ->required()
                                            ->options([
                                                'viewAny' => 'View Any (List all records)',
                                                'view' => 'View (View single record)',
                                                'create' => 'Create (Create new record)',
                                                'update' => 'Update (Edit existing record)',
                                                'delete' => 'Delete (Soft delete record)',
                                                'restore' => 'Restore (Restore deleted record)',
                                                'forceDelete' => 'Force Delete (Permanently delete)',
                                                'deleteAny' => 'Delete Any (Bulk delete)',
                                                'restoreAny' => 'Restore Any (Bulk restore)',
                                                'forceDeleteAny' => 'Force Delete Any (Bulk force delete)',
                                            ])
                                            ->columns(2)
                                            ->gridDirection('row')
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('Add Role Permission')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable()
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            )
                            ->itemLabel(fn (array $state): ?string => 
                                isset($state['role']) 
                                    ? $this->getRoleName($state['role']) . ' (' . count($state['permissions'] ?? []) . ' permissions)'
                                    : 'New Role Permission'
                            )
                            ->defaultItems(0)
                            ->collapsed(false)
                            ->minItems(1),
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->collapsed(false),
            ])
            ->statePath('data');
    }

    /**
     * Get available models from the app/Models directory
     * Only returns models that don't have existing policies
     */
    protected function getAvailableModels(): array
    {
        $modelsPath = app_path('Models');
        
        if (!File::exists($modelsPath)) {
            return [];
        }

        $models = [];
        $files = File::files($modelsPath);

        foreach ($files as $file) {
            $fileName = $file->getFilenameWithoutExtension();
            
            // Skip User model as it typically has its own policy structure
            if ($fileName === 'User') {
                continue;
            }

            // Check if policy already exists for this model
            if ($this->policyExists($fileName)) {
                continue;
            }

            $models[$fileName] = $fileName;
        }

        return $models;
    }

    /**
     * Check if a policy already exists for the given model
     */
    protected function policyExists(string $modelName): bool
    {
        $policyPath = app_path('Policies/' . $modelName . 'Policy.php');
        return File::exists($policyPath);
    }

    /**
     * Get policy statistics (total models vs models with policies)
     */
    protected function getPolicyStatistics(): array
    {
        $modelsPath = app_path('Models');
        
        if (!File::exists($modelsPath)) {
            return [
                'total' => 0,
                'with_policy' => 0,
                'without_policy' => 0,
            ];
        }

        $files = File::files($modelsPath);
        $total = 0;
        $withPolicy = 0;

        foreach ($files as $file) {
            $fileName = $file->getFilenameWithoutExtension();
            
            // Skip User model
            if ($fileName === 'User') {
                continue;
            }

            $total++;
            
            if ($this->policyExists($fileName)) {
                $withPolicy++;
            }
        }

        return [
            'total' => $total,
            'with_policy' => $withPolicy,
            'without_policy' => $total - $withPolicy,
        ];
    }

    /**
     * Get list of existing policies (model names)
     */
    protected function getExistingPolicies(): array
    {
        $modelsPath = app_path('Models');
        
        if (!File::exists($modelsPath)) {
            return [];
        }

        $existingPolicies = [];
        $files = File::files($modelsPath);

        foreach ($files as $file) {
            $fileName = $file->getFilenameWithoutExtension();
            
            // Skip User model
            if ($fileName === 'User') {
                continue;
            }

            if ($this->policyExists($fileName)) {
                $existingPolicies[] = $fileName;
            }
        }

        return $existingPolicies;
    }

    /**
     * Get available roles
     */
    protected function getAvailableRoles(): array
    {
        try {
            return Role::pluck('name', 'id')->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get role name by ID
     */
    protected function getRoleName(string $roleId): string
    {
        $role = Role::find($roleId);
        return $role ? $role->name : 'Unknown Role';
    }

    /**
     * Generate policy
     */
    public function generate(): void
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

        // Validate role permissions
        if (empty($data['rolePermissions']) || count($data['rolePermissions']) === 0) {
            Notification::make()
                ->title('Error')
                ->body('At least one role permission is required')
                ->danger()
                ->send();
            return;
        }

        // Validate each role permission
        foreach ($data['rolePermissions'] as $rolePermission) {
            if (empty($rolePermission['role']) || empty($rolePermission['permissions'])) {
                Notification::make()
                    ->title('Error')
                    ->body('All role permissions must have a role and at least one permission selected')
                    ->danger()
                    ->send();
                return;
            }
        }

        try {
            $rolePermissionsFormatted = $this->formatRolePermissions($data['rolePermissions']);
            $rolePermissionsJson = json_encode($rolePermissionsFormatted);

            // Create a unique identifier for this generation process
            $processId = uniqid();
            $resultFilePath = storage_path('app/policy_result_' . $processId . '.json');

            // Call the policy generation command
            $stepId = uniqid();
            
            Artisan::call('builder:generate-policy', [
                'modelName' => $data['modelName'],
                'stepId' => $stepId,
                'rolePermissions' => $rolePermissionsJson,
            ]);

            // Wait for the result file to be created (max 10 seconds)
            $result = $this->waitForResult($stepId, 10);
            
            if ($result && isset($result['success']) && $result['success'] === true) {
                $this->handleSuccess($result, $data['modelName']);
            } else {
                $this->handleError($result);
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Policy generation failed: ' . $e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    /**
     * Format role permissions for the command
     */
    protected function formatRolePermissions(array $rolePermissions): array
    {
        $formatted = [];
        
        foreach ($rolePermissions as $rolePermission) {
            $roleName = $this->getRoleName($rolePermission['role']);
            $formatted[$roleName] = $rolePermission['permissions'];
        }
        
        return $formatted;
    }

    /**
     * Wait for the result file to be created and return its contents
     */
    protected function waitForResult(string $stepId, int $timeoutSeconds = 10): ?array
    {
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        $maxAttempts = $timeoutSeconds * 2; // Check every 0.5 seconds
        $attempt = 0;
        $result = null;

        while ($attempt < $maxAttempts) {
            if (file_exists($stepResultFile)) {
                $resultJson = file_get_contents($stepResultFile);
                $result = json_decode($resultJson, true);
                
                // Clean up the result file
                @unlink($stepResultFile);
                break;
            }
            
            usleep(500000); // Sleep for 0.5 seconds
            $attempt++;
        }

        // Check if we timed out
        if ($result === null && $attempt >= $maxAttempts) {
            return [
                'success' => false,
                'message' => 'Operation timed out after ' . $timeoutSeconds . ' seconds.'
            ];
        }

        return $result;
    }

    /**
     * Handle successful generation
     */
    protected function handleSuccess(array $result, string $modelName): void
    {
        // Reset form
        $this->form->fill([
            'modelName' => '',
            'rolePermissions' => [],
        ]);

        // Show success notification
        $message = "Policy for {$modelName} has been generated successfully with role-based permissions.";
        
        Notification::make()
            ->title('Success!')
            ->body($message)
            ->success()
            ->duration(5000)
            ->send();
    }

    /**
     * Handle generation error
     */
    protected function handleError(?array $result): void
    {
        $errorMessage = 'Policy generation failed. ';
        
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