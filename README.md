# ISKOLARniLNU
A web-based Scholarship Management System for Leyte Normal University (LNU) that integrates Machine Learning, OCR, and text similarity to streamline scholarship applications, document verification, review prioritization, duplicate detection, and grantee record management.

## Run locally with XAMPP

1. Place this project folder in `C:\xampp\htdocs\ISKOLARniLNU`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/ISKOLARniLNU/`. This opens the student portal.
4. The portal connects to the `lnu_scholarship_db` MySQL database using the XAMPP defaults in `STUDENT_DASHBOARD/includes/db_connect.php`.

The student dashboard requires the portal database schema and at least one active student account. Staff sign in through the **Staff** tab. The `administrator` role is the Super Admin; Scholarship Officers manage programs and decisions; Reviewers can review applications only.

Back up the database, then run `Registration_and_Login(UIUX)/migrations/20261030_registration_otp.sql` and `ADMIN_DASHBOARD/migrations/20261031_scholarship_admin.sql` in phpMyAdmin. The second migration adds staff roles, scholarship eligibility, and audit-log storage. Create staff accounts using `Registration_and_Login(UIUX)/create_admin.ps1` after applying the migration. Configure SMTP as described in `Registration_and_Login(UIUX)/README.md` for student review remarks to be emailed.
