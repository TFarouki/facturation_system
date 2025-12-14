# Facturation System (Inventory & Invoicing Management)

A comprehensive, multi-language web application for managing inventory, sales, purchases, and distribution. Built with the robust Laravel 12 framework and a modern Vue 3 + Quasar frontend.

## 🚀 Key Features

*   **Multi-Language Support**: Fully localized interface in **Arabic (AR)**, **English (EN)**, and **French (FR)**, including RTL support for Arabic.
*   **Inventory Management**:
    *   Track Products, Categories, and Product Families.
    *   Monitor Stock Levels and Unit Prices (Wholesale, Semi-wholesale, Retail).
    *   Manage Units of Measure.
*   **Sales & Distribution**:
    *   Create and manage **Sales Receipts** and **Delivery Notes**.
    *   Handle **Return Notes** efficiently.
    *   Track **Distributor Stock** and manage **Stock Transfers**.
*   **Purchasing**:
    *   Manage **Suppliers** and **Purchase Invoices**.
    *   Track payments and balances.
*   **Partner Management**:
    *   Dedicated modules for **Clients**, **Distributors**, and **Suppliers**.
*   **Reporting & Documents**:
    *   Generate professional **PDF Invoices, Delivery Notes, and Stock Checks**.
    *   Export data (Products, Suppliers) to **CSV/Excel**.
    *   Visual Dashboard with key metrics.
*   **User Management**: Role-based access and user administration.

## 🛠️ Technology Stack

### Backend
*   **Framework**: [Laravel 12.0](https://laravel.com)
*   **Authentication**: Laravel Sanctum
*   **Language**: PHP 8.2+

### Frontend
*   **Framework**: [Vue.js 3](https://vuejs.org/) (Composition API)
*   **UI Framework**: [Quasar Framework 2.16](https://quasar.dev/)
*   **Build Tool**: [Vite 5](https://vitejs.dev/)
*   **Localization**: [Vue I18n](https://kazupon.github.io/vue-i18n/)
*   **Styling**: SCSS, TailwindCSS 4
*   **PDF Generation**: jsPDF, html2canvas

## 📋 Prerequisites

Ensure you have the following installed on your machine:
*   [PHP](https://www.php.net/downloads) >= 8.2
*   [Composer](https://getcomposer.org/)
*   [Node.js](https://nodejs.org/) & NPM
*   [MySQL](https://www.mysql.com/) or compatible database

## ⚡ Getting Started

### 1. Quick Setup
This project includes a convenient setup script that handles dependency installation, configuration, and database migration.

```bash
# Clone the repository
git clone https://github.com/TFarouki/facturation_system.git
cd facturation_system/backend

# Run the setup script
composer run setup
```

**What `composer run setup` does:**
1.  Installs PHP dependencies (`composer install`).
2.  Copies `.env.example` to `.env` (if not exists).
3.  Generates the application key (`php artisan key:generate`).
4.  Runs database migrations (`php artisan migrate --force`).
5.  Installs Node dependencies (`npm install`).
6.  Builds the frontend assets (`npm run build`).

### 2. Manual Setup
If you prefer to run steps manually:

```bash
# Install PHP dependencies
composer install

# Environment Configuration
cp .env.example .env
# Edit .env and configure your database settings (DB_DATABASE, DB_USERNAME, etc.)

# Generate App Key
php artisan key:generate

# Run Migrations
php artisan migrate

# Install & Build Frontend
npm install
npm run build
```

## 🏃‍♂️ Running the Application

To start the development environment (Laravel server + Vite hot reload + Queue worker), simply run:

```bash
composer run dev
```

Access the application at: `http://localhost:8000`

## 🌍 Localization

Translations are managed in `resources/js/locales/`.
*   `ar.js`: Arabic translations
*   `en.js`: English translations
*   `fr.js`: French translations

To add a new language, create a new file in this directory and update `resources/js/i18n.js`.

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
