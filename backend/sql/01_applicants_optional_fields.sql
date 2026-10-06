-- Change request for the database owner (SCRUM-8):
-- Students register with only name, Student ID, email, contact number and password.
-- Birth date and sex are collected later in the profile / application form,
-- so these two columns must allow NULL.
ALTER TABLE `applicants`
  MODIFY `birth_date` date NULL DEFAULT NULL,
  MODIFY `sex` enum('Male','Female') NULL DEFAULT NULL;
