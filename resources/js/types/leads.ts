import type { AiTaskState, Option, Suggestion } from './tickets';

export type LeadListItem = {
    id: number;
    name: string;
    email: string;
    company: string | null;
    jobTitle: string | null;
    source: string;
    status: Option;
    score: Option | null;
    suggestedScore: { value: string; label: string; reason?: string } | null;
    hasFollowUpDraft: boolean;
    followUpSentAt: string | null;
    aiWorking: boolean;
    createdAt: string | null;
};

export type LeadDetail = {
    id: number;
    name: string;
    email: string;
    company: string | null;
    jobTitle: string | null;
    companySize: string | null;
    budget: string | null;
    website: string | null;
    message: string | null;
    source: string;
    status: string;
    score: Option | null;
    scoreReason: string | null;
    scoring: AiTaskState;
    followUp: AiTaskState & {
        sentSubject: string | null;
        sentBody: string | null;
        sentAt: string | null;
        sentBy: string | null;
    };
    aiWorking: boolean;
    suggestions: Record<string, Suggestion>;
    createdAt: string | null;
};
