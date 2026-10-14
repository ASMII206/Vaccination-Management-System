# VaxCare Vaccination Management System

## Setup

1. Start Apache and MySQL in XAMPP.
2. Import `vaccination_db.sql` into phpMyAdmin. This creates the database and a
   demo administrator account.
3. Open the project through `http://localhost/` and use the **Login** link.

If the database was already created before admin login was added, import the
updated `vaccination_db.sql` into that same database to create the `admins`
table and seed the administrator account.

## Demo login accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@vaxcare.com | admin12345 |
| Hospital | demohostpital@gmail.com | 12345678 |
| Parent | demoparent@gmail.com | 12345678 |
