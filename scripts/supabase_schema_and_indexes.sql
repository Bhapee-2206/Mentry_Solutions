-- =====================================================================
-- Mentry Solutions - Supabase PostgreSQL Schema, Indexes & RPC Migration
-- Idempotent script: Safe to run multiple times in Supabase SQL Editor.
-- =====================================================================

-- 1. Base Table: public.mentry_documents
CREATE TABLE IF NOT EXISTS public.mentry_documents (
    collection VARCHAR(100) NOT NULL,
    id VARCHAR(128) NOT NULL,
    data JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    PRIMARY KEY (collection, id)
);

-- 2. Core Primary & Collection Indexes
CREATE INDEX IF NOT EXISTS idx_mentry_documents_collection 
    ON public.mentry_documents (collection);

CREATE INDEX IF NOT EXISTS idx_mentry_documents_updated_at 
    ON public.mentry_documents (updated_at DESC);

-- 3. Targeted JSONB Query Indexes (Matching Active Application Filters)

-- Users & Auth queries: lookup by email and role
CREATE INDEX IF NOT EXISTS idx_mentry_users_email 
    ON public.mentry_documents ((lower(data->>'email'))) 
    WHERE collection = 'User';

CREATE INDEX IF NOT EXISTS idx_mentry_users_role 
    ON public.mentry_documents ((data->>'role')) 
    WHERE collection = 'User';

-- Trainers queries: lookup by userId, status, availability, email
CREATE INDEX IF NOT EXISTS idx_mentry_trainers_user_id 
    ON public.mentry_documents ((data->>'userId')) 
    WHERE collection = 'Trainer';

CREATE INDEX IF NOT EXISTS idx_mentry_trainers_status 
    ON public.mentry_documents ((data->>'status')) 
    WHERE collection = 'Trainer';

CREATE INDEX IF NOT EXISTS idx_mentry_trainers_availability 
    ON public.mentry_documents ((data->>'availabilityStatus')) 
    WHERE collection = 'Trainer';

-- Opportunities: lookup by status, collegeId, dates
CREATE INDEX IF NOT EXISTS idx_mentry_opps_status 
    ON public.mentry_documents ((data->>'status')) 
    WHERE collection = 'Opportunity';

CREATE INDEX IF NOT EXISTS idx_mentry_opps_created_at 
    ON public.mentry_documents ((data->>'createdAt' DESC)) 
    WHERE collection = 'Opportunity';

-- Applications: lookup by opportunityId, trainerId, status
CREATE INDEX IF NOT EXISTS idx_mentry_apps_opportunity_id 
    ON public.mentry_documents ((data->>'opportunityId')) 
    WHERE collection = 'Application';

CREATE INDEX IF NOT EXISTS idx_mentry_apps_trainer_id 
    ON public.mentry_documents ((data->>'trainerId')) 
    WHERE collection = 'Application';

CREATE INDEX IF NOT EXISTS idx_mentry_apps_status 
    ON public.mentry_documents ((data->>'status')) 
    WHERE collection = 'Application';

-- Assignments: lookup by opportunityId, trainerId, status
CREATE INDEX IF NOT EXISTS idx_mentry_assignments_opp_trainer 
    ON public.mentry_documents ((data->>'opportunityId'), (data->>'trainerId')) 
    WHERE collection = 'Assignment';

-- Notifications: high-frequency queries for unread alerts and badges
CREATE INDEX IF NOT EXISTS idx_mentry_notifs_user_unread 
    ON public.mentry_documents ((data->>'userId'), ((data->>'read')::boolean)) 
    WHERE collection = 'Notification';

CREATE INDEX IF NOT EXISTS idx_mentry_notifs_admin_unread 
    ON public.mentry_documents (((data->>'isAdminAlert')::boolean), ((data->>'read')::boolean)) 
    WHERE collection = 'Notification';

CREATE INDEX IF NOT EXISTS idx_mentry_notifs_created_desc 
    ON public.mentry_documents ((data->>'createdAt' DESC)) 
    WHERE collection = 'Notification';

-- Documents: lookup by trainerId, type, status
CREATE INDEX IF NOT EXISTS idx_mentry_docs_trainer_id 
    ON public.mentry_documents ((data->>'trainerId')) 
    WHERE collection = 'Document';

-- General GIN index on data for dynamic queries
CREATE INDEX IF NOT EXISTS idx_mentry_documents_data_gin 
    ON public.mentry_documents USING gin (data);

-- 4. Idempotency & Unique Constraints on Business Rules
-- A trainer cannot submit duplicate applications for the same opportunity
CREATE UNIQUE INDEX IF NOT EXISTS idx_uniq_opp_trainer_application 
    ON public.mentry_documents ((data->>'opportunityId'), (data->>'trainerId')) 
    WHERE collection = 'Application';

-- Unique User email
CREATE UNIQUE INDEX IF NOT EXISTS idx_uniq_user_email 
    ON public.mentry_documents ((lower(data->>'email'))) 
    WHERE collection = 'User';

-- Unique Trainer profile per User
CREATE UNIQUE INDEX IF NOT EXISTS idx_uniq_trainer_user_id 
    ON public.mentry_documents ((data->>'userId')) 
    WHERE collection = 'Trainer';

-- Unique Active Assignment per Opportunity & Trainer
-- Business Rule: ONE TRAINER + ONE OPPORTUNITY = ONE ACTIVE ASSIGNMENT
-- Note: Historical completed/relieved/cancelled assignments are permitted to exist in audit logs,
-- but active assignments are strictly unique.
CREATE UNIQUE INDEX IF NOT EXISTS idx_uniq_opp_trainer_active_assignment 
    ON public.mentry_documents ((data->>'opportunityId'), (data->>'trainerId')) 
    WHERE collection = 'Assignment' 
      AND (data->>'status') NOT IN ('COMPLETED', 'RELIEVED', 'CANCELLED');

-- 5. Atomic Sequence Increment RPC (PostgreSQL Row-Lock Safe Across All Instances)
CREATE OR REPLACE FUNCTION public.next_counter(p_counter_name text, p_initial_val int DEFAULT 1000)
RETURNS int
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    v_seq int;
BEGIN
    INSERT INTO public.mentry_documents (collection, id, data, updated_at)
    VALUES (
        'Counters',
        p_counter_name,
        jsonb_build_object('_id', p_counter_name, 'seq', p_initial_val + 1),
        timezone('utc'::text, now())
    )
    ON CONFLICT (collection, id)
    DO UPDATE SET
        data = jsonb_set(
            public.mentry_documents.data,
            '{seq}',
            to_jsonb(COALESCE((public.mentry_documents.data->>'seq')::int, p_initial_val) + 1)
        ),
        updated_at = timezone('utc'::text, now())
    RETURNING (public.mentry_documents.data->>'seq')::int INTO v_seq;

    RETURN v_seq;
END;
$$;

-- Grant execution to anon and service_role
GRANT EXECUTE ON FUNCTION public.next_counter(text, int) TO anon, authenticated, service_role;

-- 6. Generic Atomic Document Field Increment RPC (Row-Lock Safe Across Concurrent Serverless Instances)
CREATE OR REPLACE FUNCTION public.increment_field(p_collection text, p_id text, p_field text, p_amount numeric DEFAULT 1)
RETURNS numeric
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    v_new_val numeric;
BEGIN
    UPDATE public.mentry_documents
    SET
        data = jsonb_set(
            public.mentry_documents.data,
            ARRAY[p_field],
            to_jsonb(COALESCE((public.mentry_documents.data->>p_field)::numeric, 0) + p_amount)
        ),
        updated_at = timezone('utc'::text, now())
    WHERE collection = p_collection AND id = p_id
    RETURNING (public.mentry_documents.data->>p_field)::numeric INTO v_new_val;

    RETURN v_new_val;
END;
$$;

-- Grant execution to anon and service_role
GRANT EXECUTE ON FUNCTION public.increment_field(text, text, text, numeric) TO anon, authenticated, service_role;

