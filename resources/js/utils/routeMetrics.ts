import type { RoutePoint } from '@/types/routes';

export const formatDistance = (meters: number): string => {
    if (meters < 1000) {
        return `${Math.round(meters)} м`;
    }

    return `${(meters / 1000).toFixed(2).replace('.', ',')} км`;
};

export const formatElevation = (meters: number): string => `${Math.round(meters)} м`;

export const formatDuration = (minutes: number | null): string => {
    if (!minutes) {
        return '';
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours === 0) {
        return `${remainingMinutes} мин`;
    }

    if (remainingMinutes === 0) {
        return `${hours} ч`;
    }

    return `${hours} ч ${remainingMinutes} мин`;
};

export const calculateDistance = (points: RoutePoint[]): number => {
    let distance = 0;

    for (let index = 1; index < points.length; index += 1) {
        if (pointSegment(points[index - 1]) !== pointSegment(points[index])) {
            continue;
        }

        distance += distanceBetween(points[index - 1], points[index]);
    }

    return Math.round(distance);
};

export const calculateElevation = (points: RoutePoint[]): { gain: number; loss: number } => {
    let gain = 0;
    let loss = 0;

    for (let index = 1; index < points.length; index += 1) {
        const previous = points[index - 1];
        const current = points[index];

        if (pointSegment(previous) !== pointSegment(current) || previous.ele == null || current.ele == null) {
            continue;
        }

        const delta = current.ele - previous.ele;

        if (delta > 0) {
            gain += delta;
        } else {
            loss += Math.abs(delta);
        }
    }

    return {
        gain: Math.round(gain),
        loss: Math.round(loss),
    };
};

export const pointSegment = (point: RoutePoint): number => point.segment ?? 0;

export const routeSegments = (points: RoutePoint[]): RoutePoint[][] => {
    const segments: RoutePoint[][] = [];

    points.forEach((point) => {
        const segment = pointSegment(point);
        const current = segments.at(-1);

        if (!current || pointSegment(current[0]) !== segment) {
            segments.push([point]);
            return;
        }

        current.push(point);
    });

    return segments;
};

const distanceBetween = (from: RoutePoint, to: RoutePoint): number => {
    const earthRadius = 6371000;
    const lat1 = toRadians(from.lat);
    const lat2 = toRadians(to.lat);
    const deltaLat = toRadians(to.lat - from.lat);
    const deltaLng = toRadians(to.lng - from.lng);

    const a =
        Math.sin(deltaLat / 2) ** 2 +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLng / 2) ** 2;

    return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const toRadians = (degrees: number): number => (degrees * Math.PI) / 180;
