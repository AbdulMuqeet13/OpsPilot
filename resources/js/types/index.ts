export type * from './auth';
export type * from './brand';
export type * from './tickets';
export type * from './leads';

export type Toast = {
    type: 'success' | 'error' | 'info';
    message: string;
};
