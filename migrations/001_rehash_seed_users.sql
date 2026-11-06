-- Run once on a database that was created from the ORIGINAL schema.sql (MD5 hashes).
-- Replaces the two synthetic seed passwords with bcrypt hashes from password_hash().
-- Any other accounts registered under the old MD5 code must be re-created.
USE notes_lab;
UPDATE users SET password_hash = '$2y$10$W50Wc/y5gO8rx4yJLM44U.3VWBz3vJ.RwHjwrq7IwmfZbCfVBmFOS' WHERE email = 'alex@example.test';
UPDATE users SET password_hash = '$2y$10$NuFbwaZSx2T3J1Y.6.rlieSoINqzuabEreUjL/6u31yp3lcVKGl8i' WHERE email = 'sam@example.test';
