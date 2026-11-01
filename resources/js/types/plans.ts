/**
 * What a student has left in her valid plans of the studio (see
 * PresentsPlanSummary). Unlimited when any of them is.
 */
export type PlanSummary = {
    isUnlimited: boolean;
    classesLeft: number | null;
    lastDay: string;
    daysLeft: number;
    /** From the studio's warning days before it expires, unless she renewed. */
    expiresSoon: boolean;
    /** The plan she already bought, waiting its turn. */
    next: { name: string; startsOn: string } | null;
};
