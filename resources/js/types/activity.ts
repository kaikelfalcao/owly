export type ActivitySeverity = 'normal' | 'important' | 'critical';

export type ActivityResult = 'success' | 'failure';

export type ActivityEntry = {
    id: number;
    action: string;
    label: string;
    severity: ActivitySeverity;
    result: ActivityResult;
    at: string;
    user: { id: number; name: string | null } | null;
    resource: {
        key: string | null;
        label: string | null;
        id: number | null;
        name: string | null;
    } | null;
    channel: { value: string; label: string } | null;
    ip: string | null;
    user_agent: string | null;
    request_id: string | null;
    meta: {
        field: string;
        label: string;
        value: string | number | boolean | null;
    }[];
    changes: {
        field: string;
        label: string;
        hidden: boolean;
        from: string | number | boolean | null;
        to: string | number | boolean | null;
    }[];
};

export type ActivityFilters = Partial<
    Record<
        | 'period'
        | 'from'
        | 'to'
        | 'user'
        | 'action'
        | 'resource'
        | 'resource_id'
        | 'result'
        | 'severity'
        | 'q',
        string
    >
>;

export type ActivityOption = { value: string; label: string };

export type ActivityOptions = {
    users: ActivityOption[];
    actions: ActivityOption[];
    resources: ActivityOption[];
    results: ActivityOption[];
    severities: ActivityOption[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
