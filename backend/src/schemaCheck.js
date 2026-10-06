/** True when applicants.birth_date / sex are still NOT NULL (registration cannot insert yet). */
export async function applicantsRequireExtraFields(pool) {
  const [cols] = await pool.query(
    `SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND COLUMN_NAME IN ('birth_date','sex')`,
  );
  return cols.some((c) => c.IS_NULLABLE === 'NO');
}
