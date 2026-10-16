# ISKOLARniLNU
A web-based Scholarship Management System for Leyte Normal University (LNU) that integrates Machine Learning, OCR, and text similarity to streamline scholarship applications, document verification, review prioritization, duplicate detection, and grantee record management.

## Run locally with XAMPP

1. Place this project folder in `C:\xampp\htdocs\ISKOLARniLNU`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/ISKOLARniLNU/`. This opens the student portal.
4. The portal connects to the `lnu_scholarship_db` MySQL database using the XAMPP defaults in `STUDENT_DASHBOARD/includes/db_connect.php`.

The student dashboard requires the portal database schema and at least one active student account. The admin dashboard requires a staff session (`role_id = 2`); a staff sign-in page is not included yet.
