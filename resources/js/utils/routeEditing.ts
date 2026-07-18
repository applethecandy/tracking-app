import type { RoutePoint } from '@/types/routes';
import { pointSegment } from '@/utils/routeMetrics';

type SegmentGroup = {
    points: RoutePoint[];
    indexes: number[];
};

const segmentGroups = (points: RoutePoint[]): SegmentGroup[] => {
    const groups: SegmentGroup[] = [];

    points.forEach((point, index) => {
        const current = groups.at(-1);

        if (!current || pointSegment(current.points[0]) !== pointSegment(point)) {
            groups.push({ points: [{ ...point }], indexes: [index] });
            return;
        }

        current.points.push({ ...point });
        current.indexes.push(index);
    });

    return groups;
};

const flattenGroups = (groups: SegmentGroup[]): RoutePoint[] => groups.flatMap((group, segment) =>
    group.points.map((point) => ({ ...point, segment })),
);

const groupForPoint = (groups: SegmentGroup[], pointIndex: number): number =>
    groups.findIndex((group) => group.indexes.includes(pointIndex));

export const endpointIndexes = (points: RoutePoint[]): number[] => segmentGroups(points).flatMap((group) => {
    const first = group.indexes[0];
    const last = group.indexes.at(-1)!;

    return first === last ? [first] : [first, last];
});

export const isEndpoint = (points: RoutePoint[], pointIndex: number): boolean =>
    endpointIndexes(points).includes(pointIndex);

export const normalizeSegments = (points: RoutePoint[]): RoutePoint[] => flattenGroups(segmentGroups(points));

export const splitSegmentBefore = (points: RoutePoint[], pointIndex: number): RoutePoint[] => {
    if (pointIndex <= 0 || pointIndex >= points.length) {
        return points.map((point) => ({ ...point }));
    }

    let segment = 0;

    return points.map((point, index) => {
        if (
            index > 0
            && (pointSegment(points[index - 1]) !== pointSegment(point) || index === pointIndex)
        ) {
            segment += 1;
        }

        return { ...point, segment };
    });
};

export const moveEndpointToRouteEdge = (
    points: RoutePoint[],
    pointIndex: number,
    edge: 'start' | 'end',
): RoutePoint[] | null => {
    const groups = segmentGroups(points);
    const groupIndex = groupForPoint(groups, pointIndex);

    if (groupIndex === -1) {
        return null;
    }

    const group = groups[groupIndex];
    const endpointAtStart = group.indexes[0] === pointIndex;
    const endpointAtEnd = group.indexes.at(-1) === pointIndex;

    if (!endpointAtStart && !endpointAtEnd) {
        return null;
    }

    if ((edge === 'start' && endpointAtEnd) || (edge === 'end' && endpointAtStart)) {
        group.points.reverse();
        group.indexes.reverse();
    }

    groups.splice(groupIndex, 1);
    groups[edge === 'start' ? 'unshift' : 'push'](group);

    return flattenGroups(groups);
};

export const connectEndpoints = (
    points: RoutePoint[],
    firstPointIndex: number,
    secondPointIndex: number,
): RoutePoint[] | null => {
    const groups = segmentGroups(points);
    const firstGroupIndex = groupForPoint(groups, firstPointIndex);
    const secondGroupIndex = groupForPoint(groups, secondPointIndex);

    if (firstGroupIndex === -1 || secondGroupIndex === -1 || firstGroupIndex === secondGroupIndex) {
        return null;
    }

    const firstGroup = groups[firstGroupIndex];
    const secondGroup = groups[secondGroupIndex];
    const firstIsStart = firstGroup.indexes[0] === firstPointIndex;
    const firstIsEnd = firstGroup.indexes.at(-1) === firstPointIndex;
    const secondIsStart = secondGroup.indexes[0] === secondPointIndex;
    const secondIsEnd = secondGroup.indexes.at(-1) === secondPointIndex;

    if ((!firstIsStart && !firstIsEnd) || (!secondIsStart && !secondIsEnd)) {
        return null;
    }

    if (firstIsStart) {
        firstGroup.points.reverse();
    }

    if (secondIsEnd) {
        secondGroup.points.reverse();
    }

    const insertAt = Math.min(firstGroupIndex, secondGroupIndex);
    const merged: SegmentGroup = {
        points: [...firstGroup.points, ...secondGroup.points],
        indexes: [...firstGroup.indexes, ...secondGroup.indexes],
    };
    const remaining = groups.filter((_, index) => index !== firstGroupIndex && index !== secondGroupIndex);
    remaining.splice(insertAt, 0, merged);

    return flattenGroups(remaining);
};
