-- Back up lnu_scholarship_db before running this migration in phpMyAdmin.

INSERT INTO roles (role_name, description)
SELECT new_role.role_name, new_role.description
FROM (
    SELECT 'scholarship_officer' AS role_name, 'Manage scholarship programs and review applications.' AS description
    UNION ALL
    SELECT 'reviewer', 'Review and decide scholarship applications.'
) AS new_role
WHERE NOT EXISTS (
    SELECT 1 FROM roles existing_role WHERE existing_role.role_name = new_role.role_name
);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(32) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_admin_audit_actor (actor_user_id, created_at)
);

CREATE TABLE IF NOT EXISTS scholarship_program_eligibility (
    scholarship_program_id INT UNSIGNED NOT NULL,
    college_code VARCHAR(12) NOT NULL,
    program_code VARCHAR(24) NOT NULL DEFAULT '',
    PRIMARY KEY (scholarship_program_id, college_code, program_code),
    INDEX idx_scholarship_eligibility_college (college_code, program_code)
);
