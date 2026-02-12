<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GeneratePolicy extends Command
{
    protected $signature = 'builder:generate-policy {modelName} {stepId} {rolePermissions}';
    protected $description = 'Generate Policy for the model with role-based access control';

    public function handle()
    {
        $modelName = $this->argument('modelName');
        $stepId = $this->argument('stepId');
        $rolePermissionsJson = $this->argument('rolePermissions');
        
        $stepResultFile = storage_path('app/builder_step_' . $stepId . '.json');
        
        try {
            $rolePermissions = json_decode($rolePermissionsJson, true);
            
            if (!$rolePermissions || !is_array($rolePermissions)) {
                $this->writeStepResult($stepResultFile, false, 'Invalid role permissions format');
                return 1;
            }

            // Step 1: Generate Policy
            $this->info("Generating policy for {$modelName}...");
            $exitCode = Artisan::call('make:policy', [
                'name' => $modelName . 'Policy',
                '--model' => $modelName
            ]);
            
            if ($exitCode !== 0) {
                $this->writeStepResult($stepResultFile, false, 'Failed to generate policy');
                return 1;
            }

            $policyPath = app_path('Policies/' . $modelName . 'Policy.php');
            
            if (!File::exists($policyPath)) {
                $this->writeStepResult($stepResultFile, false, 'Policy file was not created');
                return 1;
            }

            // Step 2: Modify Policy with role-based access control
            $policyModified = $this->modifyPolicy($policyPath, $modelName, $rolePermissions);
            
            if (!$policyModified) {
                $this->writeStepResult($stepResultFile, false, 'Failed to modify policy');
                return 1;
            }

            $this->info("Policy modified successfully");

            // Success
            $this->writeStepResult($stepResultFile, true, 'Policy generated and modified successfully', [
                'policyPath' => $policyPath
            ]);
            
            return 0;

        } catch (\Exception $e) {
            $this->writeStepResult($stepResultFile, false, 'Exception: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Modify the policy file to include role-based access control
     */
    protected function modifyPolicy(string $policyPath, string $modelName, array $rolePermissions): bool
    {
        try {
            $content = File::get($policyPath);
            
            // Build policy methods
            $policyMethods = $this->generatePolicyMethods($modelName, $rolePermissions);
            
            // Find the class body and replace methods
            $pattern = '/(class\s+' . $modelName . 'Policy\s*\{)(.*?)(\})\s*$/s';
            
            if (preg_match($pattern, $content, $matches)) {
                $newContent = $matches[1] . "\n" . $policyMethods . "\n" . $matches[3];
                $content = preg_replace($pattern, $newContent, $content);
            } else {
                $this->error('Could not find class body in policy file');
                return false;
            }

            File::put($policyPath, $content);
            return true;
            
        } catch (\Exception $e) {
            $this->error('Exception in modifyPolicy: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate policy methods based on role permissions
     */
    protected function generatePolicyMethods(string $modelName, array $rolePermissions): string
    {
        $modelVariable = Str::camel($modelName);
        $methods = [];

        // Define all policy actions
        $policyActions = [
            'viewAny' => [
                'hasModelParam' => false,
                'comment' => "Determine whether the user can view any " . Str::plural(strtolower($modelName)) . "."
            ],
            'view' => [
                'hasModelParam' => true,
                'comment' => "Determine whether the user can view the " . strtolower($modelName) . "."
            ],
            'create' => [
                'hasModelParam' => false,
                'comment' => "Determine whether the user can create " . Str::plural(strtolower($modelName)) . "."
            ],
            'update' => [
                'hasModelParam' => true,
                'comment' => "Determine whether the user can update the " . strtolower($modelName) . "."
            ],
            'delete' => [
                'hasModelParam' => true,
                'comment' => "Determine whether the user can delete the " . strtolower($modelName) . "."
            ],
            'restore' => [
                'hasModelParam' => true,
                'comment' => "Determine whether the user can restore the " . strtolower($modelName) . "."
            ],
            'forceDelete' => [
                'hasModelParam' => true,
                'comment' => "Determine whether the user can permanently delete the " . strtolower($modelName) . "."
            ],
            'deleteAny' => [
                'hasModelParam' => false,
                'comment' => "Determine whether the user can delete any " . Str::plural(strtolower($modelName)) . "."
            ],
            'restoreAny' => [
                'hasModelParam' => false,
                'comment' => "Determine whether the user can restore any " . Str::plural(strtolower($modelName)) . "."
            ],
            'forceDeleteAny' => [
                'hasModelParam' => false,
                'comment' => "Determine whether the user can permanently delete any " . Str::plural(strtolower($modelName)) . "."
            ],
        ];

        // Generate methods for each action
        foreach ($policyActions as $action => $config) {
            $rolesForAction = $this->getRolesForAction($rolePermissions, $action);
            
            $methods[] = $this->generateMethod(
                $action,
                $rolesForAction,
                $config['hasModelParam'],
                $modelVariable,
                $modelName,
                $config['comment']
            );
        }

        return implode("\n\n", $methods);
    }

    /**
     * Generate a single policy method
     */
    protected function generateMethod(
        string $methodName, 
        array $roles, 
        bool $hasModelParam, 
        string $modelVariable,
        string $modelName,
        string $comment
    ): string {
        $modelParam = $hasModelParam ? ", {$modelName} \${$modelVariable}" : '';
        
        // Format roles array for hasAnyRole
        $rolesString = $this->formatRolesArray($roles);
        
        $method = "    /**\n";
        $method .= "     * {$comment}\n";
        $method .= "     */\n";
        $method .= "    public function {$methodName}(User \$user{$modelParam}): bool\n";
        $method .= "    {\n";
        $method .= "        return \$user->hasAnyRole([{$rolesString}]);\n";
        $method .= "    }";
        
        return $method;
    }

    /**
     * Get roles that have access to a specific action
     */
    protected function getRolesForAction(array $rolePermissions, string $action): array
    {
        $roles = [];
        
        foreach ($rolePermissions as $roleName => $permissions) {
            if (in_array($action, $permissions)) {
                $roles[] = $roleName;
            }
        }
        
        return $roles;
    }

    /**
     * Format roles array as a string for the policy method
     */
    protected function formatRolesArray(array $roles): string
    {
        if (empty($roles)) {
            return "'no_access'"; // No roles have access
        }
        
        $formattedRoles = array_map(function($role) {
            return "'{$role}'";
        }, $roles);
        
        return implode(', ', $formattedRoles);
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