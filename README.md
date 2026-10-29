# FCWM Student Support & Complaints System

A PHP/MySQL support-ticket application for the Federal College of Wildlife Management. It reuses the Bootstrap dashboard assets in this project and provides separate student, department, and administrator workspaces.

## Run with XAMPP

1. Copy or keep this folder under `C:\xampp\htdocs`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open `http://localhost/berry-free-bootstrap-admin-template/`.
4. On first visit, complete the one-time administrator setup form. There are no default administrator credentials.
5. Sign in as the administrator and create department accounts from **Departments**. Students can register from the sign-in page.

The application automatically creates the `fcwm_complaints` database, its tables, and the initial school and service-department lists. The default XAMPP connection is `127.0.0.1:3306`, user `root`, with an empty password. If your local MySQL credentials differ, set `FCWM_DB_HOST`, `FCWM_DB_PORT`, `FCWM_DB_NAME`, `FCWM_DB_USER`, and `FCWM_DB_PASSWORD` in the PHP/Apache environment before loading the app. The database user needs permission to create the database and tables.

## Requirements

- PHP 8.1 or later with PDO MySQL enabled
- MySQL or MariaDB (the default XAMPP installation is supported)
- Apache with PHP enabled

## Workflows

- **Students:** register with full name, unique matric number, Gmail address, school, and password; submit a categorized request; receive a unique `FCWM-YYYY-...` reference; view requests while signed in or track a reference on the public tracking page.
- **Administrators:** review all requests, route a request to a service department, view the college-wide case list, and create department accounts.
- **Department staff:** view only cases assigned to their department and publish a status and message update for the student.
- **Case statuses:** Submitted, Under review, In progress, Resolved, and Closed. Status messages are retained in the case history.

The initial school and service-department names are seeded in `app/bootstrap.php`. Edit those lists before first launch if the college needs different names. The database is initialized automatically when the app is opened.

## Database connection

The local defaults are defined at the top of `app/bootstrap.php`. For a non-default XAMPP MySQL password, set `FCWM_DB_PASSWORD` in the environment used by Apache/PHP, or update the local configuration there. Do not deploy the default XAMPP database credentials to a public server.

## Included frontend template

The existing Berry dashboard source and built frontend assets remain in `src/` and `dist/`. The PHP system loads the local Bootstrap bundle and Tabler icons from `dist/assets/`.
