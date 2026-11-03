export type Option = { value: string; label: string };

export type CategoryBadgeData = { id: number; name: string; color: string };

export type SuggestionValue = {
    value: string | number;
    label: string;
    score?: number;
    sources?: string[];
    reason?: string;
    signals?: string[];
    subject?: string;
};

export type SuggestionDecision =
    | 'pending'
    | 'accepted'
    | 'edited'
    | 'rejected'
    | 'auto_applied'
    | 'superseded';

export type Suggestion = {
    id: number;
    field: string;
    fieldLabel: string;
    decision: SuggestionDecision;
    decisionLabel: string;
    suggested: SuggestionValue;
    final: SuggestionValue | null;
    decidedBy?: string | null;
    decidedAt: string | null;
    createdAt: string | null;
};

export type AiTaskState = {
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'skipped';
    error: string | null;
};

export type TicketListItem = {
    id: number;
    reference: string;
    subject: string;
    requesterName: string;
    source: string;
    status: Option;
    priority: Option | null;
    category: CategoryBadgeData | null;
    sentiment: Option | null;
    summary: string | null;
    suggested: {
        priority: SuggestionValue | null;
        category: SuggestionValue | null;
        sentiment: SuggestionValue | null;
        summary: SuggestionValue | null;
    };
    pendingSuggestions: number;
    aiWorking: boolean;
    assignee: string | null;
    lastActivityAt: string | null;
};

export type TicketMessage = {
    id: number;
    authorType: 'customer' | 'agent';
    authorName: string;
    body: string;
    wasEmailed: boolean;
    sentAt: string;
};

export type TicketDetail = {
    id: number;
    reference: string;
    subject: string;
    requesterName: string;
    requesterEmail: string;
    source: string;
    status: string;
    priority: string | null;
    categoryId: number | null;
    category: CategoryBadgeData | null;
    sentiment: { value: string; label: string; score: number } | null;
    language: { value: string; label: string } | null;
    summary: string | null;
    assignedTo: number | null;
    triage: AiTaskState;
    draft: AiTaskState;
    aiWorking: boolean;
    suggestions: Record<string, Suggestion>;
    messages: TicketMessage[];
    createdAt: string | null;
    resolvedAt: string | null;
};
