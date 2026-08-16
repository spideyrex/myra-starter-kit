/**
 * Skeleton geometry is a contract, not a decoration: a skeleton that resizes on
 * load feels SLOWER than a spinner, because the page jumps. These constants are
 * the single source of truth shared by the skeletons, the widgets that reserve
 * space for them, and the geometry test.
 *
 * STAT_HEIGHT mirrors StatCard's default card exactly:
 *   border 2 + padding 40 + title 20 + gap 4 + value 32 + gap 8 + meta 16 = 122
 */
export const STAT_HEIGHT = 122;

/** ChartWidget's default drawing area. */
export const CHART_HEIGHT = 240;

/** Matches config('myra.performance.row_height'). */
export const ROW_HEIGHT = 44;

export const TABLE_HEADER_HEIGHT = 40;
