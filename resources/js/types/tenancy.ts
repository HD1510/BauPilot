export type CompanyRole = 'admin' | 'office' | 'site';

export const companyRoleLabels: Record<CompanyRole, string> = {
    admin: 'Administrator',
    office: 'Büro',
    site: 'Baustelle',
};

export type CompanySummary = {
    id: number;
    name: string;
    short_code: string;
    color: string;
    role?: CompanyRole | null;
};

export type Tenancy = {
    activeCompany: CompanySummary | null;
    companies: CompanySummary[];
    role: CompanyRole | null;
    canViewFinancials: boolean;
};
