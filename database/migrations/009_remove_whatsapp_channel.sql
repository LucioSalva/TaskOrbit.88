-- ============================================================
-- Migration 009 — Remove WhatsApp channel from notifications
-- Applies to: notifications table, channel CHECK constraint
-- ============================================================
-- Context: WhatsApp was removed from the system entirely.
-- This migration removes 'whatsapp' (and 'push') from the
-- channel CHECK constraint and deletes any rows that used
-- those channels (they were never real — only test/mock data).
-- ============================================================

-- Step 1: Remove rows with non-supported channels
DELETE FROM notifications WHERE channel NOT IN ('in_app', 'email');

-- Step 2: Drop the old constraint (which included 'whatsapp', 'push')
ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_channel_check;

-- Step 3: Add the corrected constraint
ALTER TABLE notifications
    ADD CONSTRAINT notifications_channel_check
    CHECK (channel IN ('in_app', 'email'));
