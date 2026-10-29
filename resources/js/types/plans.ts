/**
 * What a student has left in her valid plans of the studio (see
 * PresentsPlanSummary). Unlimited when any of them is.
 */
export type PlanSummary = {
    isUnlimited: boolean;
    classesLeft: number | null;
    lastDay: string;
    daysLeft: number;
};
