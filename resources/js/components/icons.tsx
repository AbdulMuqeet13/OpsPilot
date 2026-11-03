import type { SVGProps } from 'react';

export type IconProps = SVGProps<SVGSVGElement>;

/**
 * Small inline icon set (24px outline, 1.75 stroke) so the app has no icon dependency.
 */
function createIcon(paths: string[]) {
    return function Icon({ className = 'size-5', ...props }: IconProps) {
        return (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth={1.75}
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
                className={className}
                {...props}
            >
                {paths.map((d) => (
                    <path key={d} d={d} />
                ))}
            </svg>
        );
    };
}

export const HomeIcon = createIcon([
    'M3 10.5 12 3l9 7.5',
    'M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5',
]);
export const InboxIcon = createIcon([
    'M3 13h5l1.5 3h5L16 13h5',
    'M5.5 5h13L21 13v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6z',
]);
export const UsersIcon = createIcon([
    'M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20',
    'M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z',
    'M20 20v-1.5a3.5 3.5 0 0 0-2.5-3.35',
    'M15.5 4.15a3.5 3.5 0 0 1 0 6.7',
]);
export const ChartIcon = createIcon([
    'M4 20V10',
    'M10 20V4',
    'M16 20v-7',
    'M22 20H2',
]);
export const SparklesIcon = createIcon([
    'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z',
    'M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z',
]);
export const ActivityIcon = createIcon(['M3 12h4l3-8 4 16 3-8h4']);
export const SettingsIcon = createIcon([
    'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
]);
export const MenuIcon = createIcon(['M4 6h16', 'M4 12h16', 'M4 18h16']);
export const XIcon = createIcon(['M18 6 6 18', 'M6 6l12 12']);
export const LogoutIcon = createIcon([
    'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4',
    'M16 17l5-5-5-5',
    'M21 12H9',
]);
export const CheckCircleIcon = createIcon([
    'M22 11.1V12a10 10 0 1 1-5.9-9.1',
    'M22 4 12 14l-3-3',
]);
export const AlertIcon = createIcon([
    'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    'M12 9v4',
    'M12 17h.01',
]);
export const InfoIcon = createIcon([
    'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
    'M12 16v-4',
    'M12 8h.01',
]);
export const ClockIcon = createIcon([
    'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
    'M12 6v6l4 2',
]);
export const UploadIcon = createIcon([
    'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4',
    'M17 8l-5-5-5 5',
    'M12 3v12',
]);
export const PaletteIcon = createIcon([
    'M12 22a10 10 0 1 1 10-10c0 2.8-2.2 4-4 4h-1.8a1.5 1.5 0 0 0-1.1 2.5 1.5 1.5 0 0 1-1.1 2.5z',
    'M7.5 10.5h.01',
    'M12 7.5h.01',
    'M16.5 10.5h.01',
]);
export const UserIcon = createIcon([
    'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2',
    'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
]);
export const ShieldIcon = createIcon([
    'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
    'M9 12l2 2 4-4',
]);
export const ArrowRightIcon = createIcon(['M5 12h14', 'M12 5l7 7-7 7']);
export const ChevronDownIcon = createIcon(['M6 9l6 6 6-6']);
