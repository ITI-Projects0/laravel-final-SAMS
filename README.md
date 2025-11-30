# SAMS - Student Attendance Management System (Backend)

## Overview
This is the backend API for the Student Attendance Management System (SAMS), built with **Laravel 11**. It provides robust authentication, role-based access control (RBAC), and management features for centers, students, teachers, and parents.

## 🚀 Key Features

### Authentication & Security
- **Sanctum Authentication**: Secure API token management.
- **Google Login**: Integrated via Laravel Socialite with a **Secure Exchange Token Flow** to prevent token exposure in URLs.
- **Role-Based Access Control (RBAC)**: Powered by `spatie/laravel-permission`.
- **Secure Password Reset**: Token-based password reset flow via email.
- **Email Verification**: Activation code system for new registrations.

### User Management
- **Multi-Role Support**: Users can have multiple roles (e.g., `center_admin` + `teacher`).
- **Default Roles**: New users are automatically assigned `center_admin` and `teacher` roles.
- **User Status**: Active/Inactive status management.

## 🛠️ Tech Stack
- **Framework**: Laravel 11
- **Database**: MySQL
- **Auth**: Laravel Sanctum, Laravel Socialite
- **Permissions**: Spatie Laravel Permission
- **API Documentation**: Postman / Swagger (Planned)

## ⚙️ Setup & Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd laravel-final-SAMS
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   ```
   Update `.env` with your database and mail credentials:
   ```env
   DB_DATABASE=sams_db
   
   GOOGLE_CLIENT_ID=your-google-client-id
   GOOGLE_CLIENT_SECRET=your-google-client-secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   
   APP_FRONTEND_URL=http://localhost:35045
   ```

4. **Generate Key**
   ```bash
   php artisan key:generate
   ```

5. **Run Migrations & Seeders**
   ```bash
   php artisan migrate --seed
   ```

6. **Serve the Application**
   ```bash
   php artisan serve
   ```

## 🔌 API Endpoints

### Authentication (`/api/auth`)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/register` | Register a new user (Auto-assigns roles). |
| POST | `/login` | Login with email & password. |
| POST | `/logout` | Revoke current access token. |
| GET | `/me` | Get current authenticated user details. |
| GET | `/google` | Redirect to Google OAuth. |
| GET | `/google/callback` | Handle Google callback & return exchange token. |
| POST | `/exchange-token` | Exchange temporary token for JWT (Secure Flow). |
| POST | `/verify-email` | Verify user email with activation code. |
| POST | `/send-reset-code` | Send password reset code to email. |
| POST | `/validate-reset-code` | Validate reset code. |
| POST | `/reset-password` | Set new password. |

## 🛡️ Security Note
**Google Login Flow**: We do NOT send the JWT token directly in the URL callback. Instead, we generate a short-lived `exchange_token` (valid for 60s) stored in Cache. The frontend must exchange this token via a POST request to `/api/auth/exchange-token` to receive the actual authentication token.
