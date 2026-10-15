-- Preserve service records and historical enquiries; remove retired public offerings.
UPDATE services
SET is_published = 0
WHERE CONCAT(slug, ' ', name) REGEXP '(^|[^[:alnum:]])(hdb|bca)([^[:alnum:]]|$)';
