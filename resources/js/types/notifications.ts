export type AppNotification = {
    id: string;
    kind: string;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    at: string | null;
};

export type NotificationFeed = {
    unread: number;
    items: AppNotification[];
};
