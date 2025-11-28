# Backend Change Report

This document summarizes the recent modifications made to the SAMS Backend (Laravel), specifically focusing on the Authentication Revamp and User Management updates.

## 1. Database Schema Changes
**Migration:** `2025_11_27_035933_add_auth_fields_to_users_table.php`
Added the following columns to the `users` table:
- `activation_code` (string, nullable): Stores the code used for email verification.
- `is_data_complete` (boolean, default: false): Flag to indicate if the user has completed their profile (phone, role, etc.).
- `google_id` (string, nullable): Stores the Google User ID for OAuth login.

## 2. Authentication Logic (`AuthController.php`)
A comprehensive `AuthController` has been implemented with the following features:

### Registration & Verification
- **Register (`POST /api/auth/register`)**:
  - Creates a user with `status: pending`.
  - Generates a UUID `activation_code`.
  - Sends an **Activation Email** and an **Incomplete Profile Warning Email**.
  - Returns an authentication token immediately.
- **Verify Email (`POST /api/auth/verify-email`)**:
  - Accepts `code`.
  - Verifies the user and updates `status` to `active`.

### Login & Security
- **Login (`POST /api/auth/login`)**:
  - Validates credentials.
  - **Check:** Ensures user `status` is `active`.
  - **Check:** Prevents concurrent logins (returns 403 if user already has an active token).
- **Logout (`POST /api/auth/logout`)**:
  - Revokes current access token.

### Google OAuth
- **Redirect (`GET /auth/google`)**: Redirects to Google.
- **Callback (`GET /auth/google/callback`)**:
  - Handles the response from Google.
  - Creates a new user if one doesn't exist (with random password).
  - Updates existing users with `google_id`.
  - Redirects to the frontend (`/login?token=...`).

### Profile Management
- **Complete Profile (`POST /api/auth/complete-profile`)**:
  - Updates `phone` and `role`.
  - Sets `is_data_complete` to `true`.

### Password Reset
- **Send Code (`POST /api/auth/send-reset-code`)**: Sends a reset link via email.
- **Reset Password (`POST /api/auth/reset-password`)**: Verifies token and updates password.

## 3. User Model Updates (`User.php`)
- **Fillable Fields**: Added `activation_code`, `is_data_complete`, `google_id`.
- **Helper Methods**: Added `isAdmin()`, `isTeacher()`, `isStudent()`, `isActive()`, etc.
- **Scopes**: Added `scopeIncomplete()` to easily find users with incomplete profiles.

## 4. API Routes (`routes/api.php`)
New routes added under `auth` prefix:
- `POST /register`
- `POST /login`
- `POST /verify-email`
- `POST /send-reset-code`
- `POST /reset-password`
- `POST /complete-profile` (Authenticated)
- `POST /logout` (Authenticated)
- `GET /me` (Authenticated)

## 5. Untracked / New Files
The following new components were added (currently untracked by git):
- **Mailables**: `ActivationCodeMail`, `IncompleteProfileWarningMail`, `ResetCodeMail`.
- **Tests**: `tests/Feature/AuthRevampTest.php`.
