# Add indices to improve performance.
CREATE INDEX `status` ON `url` (status);
CREATE INDEX `website_test_results_id` ON `url` (website_test_results_id);
