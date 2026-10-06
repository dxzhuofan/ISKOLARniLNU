// The database calls students "applicant"; the API and frontend call them "student".
const DB_TO_API = { applicant: 'student', administrator: 'administrator' };
export const toApiRole = (dbName) => DB_TO_API[dbName];
export const API_ROLES = ['student', 'administrator'];
