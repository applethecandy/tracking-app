export type ActivityMap = Record<string, string>;

export type RoutePoint = {
    lat: number;
    lng: number;
    ele?: number | null;
    segment?: number | null;
};

export type TrackRoute = {
    id: number;
    title: string;
    activity_date: string;
    duration_minutes: number | null;
    activity_type: string;
    activity_label: string;
    comment: string | null;
    points: RoutePoint[];
    distance_m: number;
    elevation_gain_m: number;
    elevation_loss_m: number;
    is_shared: boolean;
    share_url?: string | null;
    gpx_url?: string;
};
