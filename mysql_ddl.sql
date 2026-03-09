-- =============================================================================
-- DDL for Advanced Features (Drag-and-Drop Reordering)
-- =============================================================================
-- For models needing drag-and-drop reordering (production use):
-- Add a sort_order column to any table that needs reorderable rows.

-- Example: Add sort_order to a generic table
-- ALTER TABLE `your_table` ADD COLUMN `sort_order` INT UNSIGNED DEFAULT 0 AFTER `id`;
-- CREATE INDEX `idx_sort_order` ON `your_table` (`sort_order`);

-- No demo migrations needed — all demos use in-memory collections.
-- =============================================================================
