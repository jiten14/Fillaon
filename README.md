# FILLAON

**Full Filament Admin Panel Application**

Build Powerful Laravel Applications with GUI Builders

---

## Table of Contents

- [Introduction](#introduction)
- [Prerequisites](#prerequisites)
- [Installation Guide](#installation-guide)
- [Initial Setup & Configuration](#initial-setup--configuration)
- [First Login](#first-login)
- [Dashboard Overview](#dashboard-overview)
- [Resource Builder](#resource-builder)
- [Policy Generator](#policy-generator)
- [User Management](#user-management)
- [Role & Permission Management](#role--permission-management)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)
- [Future Roadmap](#future-roadmap)

---

## Introduction

Welcome to **Fillaon**! Fillaon is a powerful, ready-to-use Laravel application built on top of Filament Admin Panel with Spatie Permission integration. It provides intuitive GUI builders that allow you to create complete Laravel applications without writing repetitive boilerplate code.

### What is Fillaon?

Fillaon eliminates the tedious task of manually creating models, migrations, resources, and policies. Instead of spending hours writing repetitive code, you can use Fillaon's visual builders to generate everything you need in minutes. Whether you're building a CRM, inventory system, project management tool, or any other database-driven application, Fillaon accelerates your development process.

### Key Features

- **Resource Builder**: Generate complete models, migrations, Filament resources, factories, and seeders with a few clicks
- **Policy Generator**: Create role-based access control policies for your models effortlessly
- **Spatie Permission Integration**: Full-featured role and permission management out of the box
- **Sample Data Generation**: Automatic creation of realistic sample data for testing
- **Pre-configured Authentication**: Login system with super admin account ready to use
- **User-Friendly Interface**: Clean, modern Filament UI for all administrative tasks

### Who Should Use Fillaon?

- Developers who want to rapidly prototype Laravel applications
- Teams building admin panels or internal tools
- Anyone tired of writing repetitive CRUD code
- Projects requiring quick MVP development
- Applications needing robust role-based access control

---

## Prerequisites

Before installing Fillaon, ensure your development environment meets the following requirements:

### System Requirements

- **PHP 8.1 or higher** (PHP 8.2 or 8.3 recommended)
- **Composer** (latest version)
- **Database**: MySQL 8.0+, PostgreSQL 13+, SQLite 3.35+, or SQL Server 2017+
- **Node.js & NPM** (for asset compilation)
- **Git** (for cloning the repository)

### Required PHP Extensions

- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- GD or Imagick (for image processing)

### Knowledge Prerequisites

While Fillaon makes Laravel development easier, basic familiarity with the following is helpful:

- Laravel framework basics
- Database concepts (tables, columns, relationships)
- Command line/terminal usage
- Basic understanding of MVC architecture

---

## Installation Guide

Follow these step-by-step instructions to get Fillaon running on your local machine.

### Step 1: Clone the Repository

Open your terminal and navigate to the directory where you want to install Fillaon, then run:

```bash
git clone https://github.com/jiten14/Fillaon.git fillaon
cd fillaon
```

### Step 2: Install Dependencies

Install PHP dependencies using Composer:

```bash
composer install
```

Install Node.js dependencies (if applicable):

```bash
npm install
```

### Step 3: Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

---

## Initial Setup & Configuration

### Database Configuration

Open the `.env` file in your preferred text editor and configure your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

#### Database Configuration Examples

**For MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fillaon
```

**For PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fillaon
```

**For SQLite (Development):**
```env
DB_CONNECTION=sqlite
# Leave other DB_ variables commented out
```

### Run Migrations

After configuring your database, run the migrations to create all necessary tables:

```bash
php artisan migrate
```

This command will create all required database tables including users, roles, permissions, and system tables for Fillaon's builders.

### Seed Default Data

Run the user seeder to create the default super admin account:

```bash
php artisan db:seed --class=UserSeeder
```

This creates a super admin user with full system access. The default credentials are:

- **Email**: admin@example.com
- **Password**: admin123

### Start the Development Server

Start the Laravel development server:

```bash
php artisan serve
```

The application will be available at **http://localhost:8000**

---

## First Login

### Accessing the Admin Panel

Navigate to the admin panel by visiting:

```
http://localhost:8000/admin
```

### Login with Default Credentials

Use the following credentials to log in:

- **Email**: admin@example.com
- **Password**: admin123

### ⚠️ Important Security Note

**WARNING**: Change the default password immediately after your first login! Go to your profile settings and update the password to something secure. Never use the default credentials in a production environment.

---

## Dashboard Overview

After logging in, you'll see the Fillaon dashboard. The interface is organized with a sidebar navigation on the left and the main content area on the right.

### Navigation Structure

The sidebar is organized into several navigation groups:

- **Dashboard**: Overview and statistics
- **Builder**: Contains Resource Builder and Policy Generator
- **Access Control**: User management, roles, and permissions
- **Generated Resources**: Resources you create will appear here dynamically

### Builder Nav Group

The Builder nav group is the heart of Fillaon. It contains two powerful tools:

1. **Resource Builder**: Generate complete models, migrations, Filament resources, factories, and seeders
2. **Policy Generator**: Create role-based access control policies for your models

---

## Resource Builder

The Resource Builder is Fillaon's flagship feature. It allows you to generate complete Laravel resources without writing a single line of code manually.

### What the Resource Builder Creates

When you use the Resource Builder, it generates:

- **Model**: Eloquent model with fillable properties and casting
- **Migration**: Database migration with all defined fields and indexes
- **Filament Resource**: Complete admin panel resource with form, table, and validation
- **Factory**: Model factory for generating realistic test data
- **Seeder**: Database seeder with sample records

### Using the Resource Builder

#### Step 1: Access the Builder

Click on **Builder** in the sidebar, then select **Resource Builder**.

#### Step 2: Define Your Resource

Click the **Create Resource** button. You'll see a form with the following fields:

- **Resource Name**: The singular name of your resource (e.g., Product, Customer, Order)
- **Table Name**: Database table name (auto-generated from resource name)
- **Navigation Group**: Where this resource appears in the admin sidebar

#### Step 3: Add Fields

Click **Add Field** to define the columns for your resource. For each field, specify:

- **Field Name**: Column name (e.g., name, email, price, quantity)
- **Field Type**: Data type (string, text, integer, decimal, boolean, date, datetime, etc.)
- **Length/Precision**: For strings and decimals
- **Required**: Whether the field is mandatory
- **Unique**: Whether values must be unique
- **Default Value**: Optional default value

#### Step 4: Configure Options

- **Generate Factory**: Enable to create a model factory
- **Generate Seeder**: Enable to create a database seeder
- **Number of Sample Records**: How many sample records to generate (if seeder is enabled)

#### Step 5: Generate

Click **Generate Resource**. Fillaon will create all the files and run migrations automatically. Your new resource will appear in the sidebar under the specified navigation group.

### Example: Creating a Product Resource

Let's create a complete Product resource:

1. Resource Name: Product
2. Table Name: products (auto-filled)
3. Navigation Group: Inventory
4. Fields:
   - name (string, required, length: 255)
   - description (text, optional)
   - price (decimal, required, precision: 10,2)
   - quantity (integer, required, default: 0)
   - sku (string, required, unique, length: 50)
   - is_active (boolean, default: true)

After generation, you'll have a fully functional Product resource with list, create, edit, and delete capabilities, complete with validation and sample data.

### Supported Field Types

- **String**: Short text fields (names, titles, SKUs)
- **Text**: Long text content (descriptions, notes)
- **Integer**: Whole numbers (quantities, counts)
- **Decimal**: Numbers with decimal points (prices, percentages)
- **Boolean**: True/false values (is_active, is_featured)
- **Date**: Date only (birth_date, expiry_date)
- **DateTime**: Date and time (published_at, scheduled_at)
- **Email**: Email addresses with validation
- **JSON**: Structured data storage

---

## Policy Generator

The Policy Generator creates Laravel policy classes that control which users can perform specific actions on your models based on their roles.

### Understanding Policies

Policies define authorization logic for your application. They answer questions like "Can this user view this record?" or "Can this user delete this resource?" Fillaon's Policy Generator makes it easy to create comprehensive policies without writing code.

### Using the Policy Generator

#### Step 1: Access the Generator

Navigate to **Builder → Policy Generator** in the sidebar.

#### Step 2: Select a Model

Click **Create Policy** and select the model you want to create a policy for. This list includes all models in your application, including those created with the Resource Builder.

#### Step 3: Configure Permissions for Each Role

For each role in your system, configure which actions they can perform:

- **viewAny**: Can view the list of records
- **view**: Can view individual records
- **create**: Can create new records
- **update**: Can edit existing records
- **delete**: Can delete records
- **restore**: Can restore soft-deleted records
- **forceDelete**: Can permanently delete records

#### Step 4: Generate the Policy

Click **Generate Policy**. Fillaon will create a policy class and automatically register it in your application. The permissions will take effect immediately.

### Example: Product Policy

Let's create a policy for the Product model with three roles:

- **Admin**: Full access (all permissions enabled)
- **Manager**: Can view, create, and update products (delete disabled)
- **Viewer**: Can only view products (viewAny and view enabled)

After generating this policy, users with different roles will see different options when accessing the Product resource. Viewers won't see create, edit, or delete buttons, while Managers will see everything except delete.

---

## User Management

Fillaon includes a complete user management system powered by Filament and Spatie Permissions.

### Creating Users

1. Navigate to **Access Control → Users**
2. Click **Create User**
3. Fill in the user details:
   - Name
   - Email
   - Password
4. Assign roles to the user
5. Click **Create**

### Editing Users

Click the edit icon next to any user to modify their details, change their password, or update their assigned roles.

### Deleting Users

Users can be deleted by clicking the delete icon. Note that deleting a user is permanent and cannot be undone.

---

## Role & Permission Management

Fillaon uses Spatie Laravel Permission for robust role-based access control.

### Understanding Roles and Permissions

- **Permissions**: Specific abilities (e.g., view_products, create_orders)
- **Roles**: Groups of permissions assigned to users (e.g., Admin, Manager, Viewer)

### Creating Roles

1. Navigate to **Access Control → Roles**
2. Click **Create Role**
3. Enter a role name (e.g., Content Editor)
4. Select permissions to assign to this role
5. Click **Create**

### Creating Permissions

1. Navigate to **Access Control → Permissions**
2. Click **Create Permission**
3. Enter a permission name (e.g., view_reports, export_data)
4. Click **Create**

### Best Practices

- Use descriptive names for roles (Admin, Manager, Editor) and permissions (view_users, delete_posts)
- Follow a naming convention for permissions (action_resource: create_product, view_order)
- Create roles based on job functions, not individual people
- Regularly audit roles and permissions to ensure they align with your security requirements

---

## Best Practices

### Resource Naming

- Use singular names for resources (Product, not Products)
- Use PascalCase for resource names (ProductCategory, UserProfile)
- Keep names clear and descriptive

### Field Naming

- Use snake_case for field names (first_name, created_at)
- Be consistent with naming conventions across your application
- Use prefixes for boolean fields (is_active, has_permission)
- Use suffixes for date/time fields (_at, _date)

### Security

- **Change default credentials immediately**
- Use strong passwords for all user accounts
- Implement the principle of least privilege (give users only the permissions they need)
- Regularly review and audit user permissions
- Keep Laravel and Filament packages up to date

### Development Workflow

- Plan your data structure before creating resources
- Use the Resource Builder for quick prototyping
- Generate sample data with seeders for testing
- Create policies after resources are stable
- Test thoroughly before deploying to production

### Database Management

- Back up your database regularly
- Use migrations for all schema changes
- Index frequently queried columns
- Keep your .env file secure and never commit it to version control

---

## Troubleshooting

### Common Issues and Solutions

#### Cannot Log In

- Verify you're using the correct default credentials (admin@example.com / admin123)
- Ensure migrations have been run (`php artisan migrate`)
- Confirm the UserSeeder has been executed (`php artisan db:seed --class=UserSeeder`)
- Clear your browser cache and cookies

#### Migration Errors

- Check database connection in .env file
- Ensure database exists and credentials are correct
- Try running: `php artisan migrate:fresh` (WARNING: This will delete all data)
- Check Laravel log files in storage/logs for detailed error messages

#### Generated Resource Not Appearing

- Clear application cache: `php artisan cache:clear`
- Clear config cache: `php artisan config:clear`
- Clear view cache: `php artisan view:clear`
- Refresh your browser

#### Permission Denied Errors

- Verify user has been assigned the correct role
- Check that the policy has been generated for the resource
- Ensure role has necessary permissions
- Try logging out and logging back in

#### Composer Dependency Issues

- Delete vendor folder and composer.lock
- Run: `composer install`
- Ensure you're using compatible PHP version (8.1+)

### Getting Help

If you encounter issues not covered here:

- Check Laravel documentation: https://laravel.com/docs
- Review Filament documentation: https://filamentphp.com/docs
- Consult Spatie Permission docs: https://spatie.be/docs/laravel-permission
- Review application logs in storage/logs

---

## Future Roadmap

Fillaon is continuously evolving. Here are the features planned for future releases:

### Upcoming Features

#### Relationship Builder

Define relationships between models through a visual interface. Create one-to-many, many-to-many, and polymorphic relationships without writing code. The builder will automatically generate migration files, model methods, and Filament relationship fields.

#### Widget Builder

Create dashboard widgets visually. Generate stats overview widgets, chart widgets, and custom widgets with drag-and-drop functionality. Display key metrics, trends, and insights without coding.

#### Advanced Resource Customizer

Fine-tune generated resources with additional customization options including custom actions, bulk actions, filters, custom columns, form layouts, validation rules, and more. All through an intuitive GUI.

### Planned Enhancements

- API endpoint generation for resources
- Import/export functionality for resources
- Advanced search and filtering options
- Multi-tenancy support
- Custom theme builder
- Activity log and audit trail
- Two-factor authentication
- Scheduled task builder

### Stay Updated

Follow the Fillaon repository for updates, feature announcements, and release notes. We're committed to making Fillaon the most powerful and user-friendly Laravel application builder available.

---

## Conclusion

Thank you for choosing Fillaon! We've designed this tool to empower developers to build robust Laravel applications faster and more efficiently. Whether you're creating a simple CRUD application or a complex multi-user system, Fillaon provides the tools you need to succeed.

We encourage you to explore all of Fillaon's features, experiment with the builders, and see how much time you can save on your next project. As you become more familiar with the system, you'll discover new ways to leverage its power.

**Happy building!**

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).