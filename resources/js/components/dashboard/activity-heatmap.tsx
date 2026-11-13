import { scaleBand } from '@visx/scale';
import { scaleSequential } from 'd3-scale';
import { interpolateBlues } from 'd3-scale-chromatic';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ActivityDay = {
    date: string;
    count: number;
};

type ActivityHeatmapProps = {
    activitySeries: ActivityDay[];
};

const periods = [
    { days: 30, label: '30 días' },
    { days: 90, label: '90 días' },
    { days: 180, label: '6 meses' },
] as const;

const weekdayLabels = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
const chartWidth = 920;
const chartHeight = 176;
const chartMargins = { top: 26, right: 8, bottom: 8, left: 32 };

function parseUtcDate(date: string): Date {
    return new Date(`${date}T00:00:00Z`);
}

function formatActivityDate(date: string): string {
    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(parseUtcDate(date));
}

function formatMonth(date: Date): string {
    return new Intl.DateTimeFormat('es-PE', {
        month: 'short',
        timeZone: 'UTC',
    }).format(date);
}

export function ActivityHeatmap({ activitySeries }: ActivityHeatmapProps) {
    const [period, setPeriod] = useState<(typeof periods)[number]['days']>(90);
    const [hoveredDay, setHoveredDay] = useState<ActivityDay | null>(null);
    const [selectedDay, setSelectedDay] = useState<ActivityDay | null>(null);
    const visibleDays = activitySeries.slice(-period);
    const firstWeekdayOffset = visibleDays.length
        ? (parseUtcDate(visibleDays[0].date).getUTCDay() + 6) % 7
        : 0;
    const weekCount = Math.max(
        1,
        Math.ceil((firstWeekdayOffset + visibleDays.length) / 7),
    );
    const weeks = Array.from({ length: weekCount }, (_, index) => index);
    const xScale = scaleBand<number>({
        domain: weeks,
        range: [chartMargins.left, chartWidth - chartMargins.right],
        padding: 0.18,
    });
    const yScale = scaleBand<number>({
        domain: [0, 1, 2, 3, 4, 5, 6],
        range: [chartMargins.top, chartHeight - chartMargins.bottom],
        padding: 0.18,
    });
    const maximumCount = Math.max(1, ...visibleDays.map((day) => day.count));
    const colorScale = scaleSequential(interpolateBlues).domain([
        0,
        maximumCount,
    ]);
    const activeDay = visibleDays.some((day) => day.date === selectedDay?.date)
        ? selectedDay
        : null;
    const summaryDay = hoveredDay ?? activeDay;
    const periodTotal = visibleDays.reduce(
        (total, day) => total + day.count,
        0,
    );
    const daysWithActivity = visibleDays.filter((day) => day.count > 0).length;
    const monthLabels = visibleDays.flatMap((day, index) => {
        const date = parseUtcDate(day.date);

        if (index !== 0 && date.getUTCDate() !== 1) {
            return [];
        }

        const weekIndex = Math.floor((index + firstWeekdayOffset) / 7);
        const x = xScale(weekIndex);

        return x === undefined ? [] : [{ x, label: formatMonth(date) }];
    });

    function selectDay(day: ActivityDay) {
        setSelectedDay((current) => (current?.date === day.date ? null : day));
    }

    return (
        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
            <CardHeader className="flex-row flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                <div>
                    <CardTitle className="text-base">
                        Ritmo de actividad
                    </CardTitle>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Registros diarios del equipo
                    </p>
                </div>
                <div
                    aria-label="Filtrar periodo del mapa de actividad"
                    className="inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800"
                    role="group"
                >
                    {periods.map((option) => (
                        <button
                            key={option.days}
                            aria-pressed={period === option.days}
                            className={`rounded-md px-3 py-1.5 text-xs font-medium transition ${
                                period === option.days
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            }`}
                            onClick={() => {
                                setPeriod(option.days);
                                setHoveredDay(null);
                                setSelectedDay(null);
                            }}
                            type="button"
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </CardHeader>
            <CardContent className="px-5 py-5">
                <div className="mb-4 flex min-h-5 flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <p aria-live="polite">
                        {summaryDay
                            ? `${formatActivityDate(summaryDay.date)} · ${summaryDay.count} ${
                                  summaryDay.count === 1
                                      ? 'actividad'
                                      : 'actividades'
                              }`
                            : `${periodTotal} actividades en ${daysWithActivity} días con registros`}
                    </p>
                    {activeDay && (
                        <button
                            className="font-medium text-sky-700 hover:underline dark:text-sky-300"
                            onClick={() => setSelectedDay(null)}
                            type="button"
                        >
                            Limpiar selección
                        </button>
                    )}
                </div>
                <div className="overflow-x-auto">
                    <svg
                        aria-label={`Mapa de calor de actividad de los últimos ${period} días`}
                        className="h-auto w-full min-w-[560px] text-slate-400"
                        role="group"
                        viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                    >
                        <title>Actividad diaria del equipo</title>
                        {monthLabels.map((month, index) => (
                            <text
                                key={`${month.label}-${index}`}
                                fill="currentColor"
                                fontSize="11"
                                x={month.x}
                                y="14"
                            >
                                {month.label}
                            </text>
                        ))}
                        {weekdayLabels.map((label, weekdayIndex) => {
                            const y = yScale(weekdayIndex);

                            return weekdayIndex % 2 === 0 && y !== undefined ? (
                                <text
                                    key={label}
                                    fill="currentColor"
                                    fontSize="10"
                                    textAnchor="end"
                                    x={chartMargins.left - 10}
                                    y={y + yScale.bandwidth() / 2 + 3}
                                >
                                    {label}
                                </text>
                            ) : null;
                        })}
                        {visibleDays.map((day, index) => {
                            const date = parseUtcDate(day.date);
                            const weekIndex = Math.floor(
                                (index + firstWeekdayOffset) / 7,
                            );
                            const weekdayIndex = (date.getUTCDay() + 6) % 7;
                            const x = xScale(weekIndex);
                            const y = yScale(weekdayIndex);
                            const isSelected = activeDay?.date === day.date;
                            const isHovered = hoveredDay?.date === day.date;

                            if (x === undefined || y === undefined) {
                                return null;
                            }

                            return (
                                <g
                                    key={day.date}
                                    aria-label={`${formatActivityDate(day.date)}: ${day.count} ${
                                        day.count === 1
                                            ? 'actividad'
                                            : 'actividades'
                                    }`}
                                    aria-pressed={
                                        selectedDay?.date === day.date
                                    }
                                    className="cursor-pointer outline-none"
                                    onBlur={() => setHoveredDay(null)}
                                    onClick={() => selectDay(day)}
                                    onFocus={() => setHoveredDay(day)}
                                    onKeyDown={(event) => {
                                        if (
                                            event.key === 'Enter' ||
                                            event.key === ' '
                                        ) {
                                            event.preventDefault();
                                            selectDay(day);
                                        }
                                    }}
                                    onMouseEnter={() => setHoveredDay(day)}
                                    onMouseLeave={() => setHoveredDay(null)}
                                    role="button"
                                    tabIndex={0}
                                >
                                    <title>
                                        {formatActivityDate(day.date)}:{' '}
                                        {day.count}{' '}
                                        {day.count === 1
                                            ? 'actividad'
                                            : 'actividades'}
                                    </title>
                                    <rect
                                        fill={
                                            day.count === 0
                                                ? 'var(--muted)'
                                                : colorScale(day.count)
                                        }
                                        height={yScale.bandwidth()}
                                        rx="3"
                                        stroke={
                                            isSelected || isHovered
                                                ? 'var(--color-sky-600)'
                                                : 'transparent'
                                        }
                                        strokeWidth={
                                            isSelected || isHovered ? 2 : 0
                                        }
                                        width={xScale.bandwidth()}
                                        x={x}
                                        y={y}
                                    />
                                </g>
                            );
                        })}
                    </svg>
                </div>
                <div className="mt-3 flex items-center justify-end gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span>Menos</span>
                    {[0, 0.25, 0.5, 0.75, 1].map((step) => (
                        <span
                            key={step}
                            aria-hidden="true"
                            className="size-3 rounded-sm"
                            style={{
                                backgroundColor:
                                    step === 0
                                        ? 'var(--muted)'
                                        : colorScale(maximumCount * step),
                            }}
                        />
                    ))}
                    <span>Más</span>
                </div>
            </CardContent>
        </Card>
    );
}
