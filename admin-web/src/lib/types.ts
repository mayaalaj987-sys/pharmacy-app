export type AdminRole = "super_admin" | "pharmacy_reviewer";

export interface AdminAccount {
  id: string;
  name: string;
  email: string;
  role: AdminRole;
  is_active: boolean;
  last_login_at: string | null;
  password_changed_at: string | null;
  disabled_at: string | null;
  created_at: string | null;
}

export interface Navigation {
  review_pharmacies: boolean;
  manage_admins: boolean;
}

export interface SessionData {
  admin: AdminAccount;
  navigation: Navigation;
}

export type PharmacyStatus = "pending" | "approved" | "rejected";
export type DocumentReviewStatus = "pending" | "approved" | "rejected" | "superseded";
export type DocumentType = "certificate" | "license";
export type MimeCategory = "pdf" | "image";

export interface PharmacyDocument {
  id: string;
  type: DocumentType;
  review_status: DocumentReviewStatus;
  mime_category: MimeCategory;
  size_bytes: number;
  submitted_at: string | null;
  reviewed_at: string | null;
  preview_url: string;
  download_url: string;
}

export interface PharmacyOwner {
  name: string;
  email: string;
  phone: string;
}

export interface PharmacyApplication {
  id: number;
  name: string;
  address: string;
  status: PharmacyStatus;
  submitted_at: string | null;
  review_version: number;
  owner?: PharmacyOwner;
  documents?: PharmacyDocument[];
  reviewed_at: string | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/** Read endpoints that always return a payload, with no message/code pair. */
export interface DataEnvelope<T> {
  data: T;
}

export interface ApiEnvelope<T> {
  message: string;
  code: string;
  data?: T;
}

export interface PharmacyFleetAnalytics {
  total_owners: number;
  owners_operating: number;
  owners_without_an_approved_pharmacy: number;
  branches: { approved: number; pending: number; rejected: number };
  distribution: {
    single_branch_owners: number;
    multi_branch_owners: number;
    single_branch_percentage: number;
    multi_branch_percentage: number;
  };
}

export interface JobMarketAnalytics {
  open_positions: number;
  active_seekers: number;
  total_applicants: number;
  hired: number;
  rejected: number;
  hire_rate_percentage: number;
  capacity: {
    employees_per_pharmacy: number;
    total_slots: number;
    filled_slots: number;
  };
}

export interface OnboardingPoint {
  month: string;
  label: string;
  registrations: number;
}

export interface OnboardingAnalytics {
  from: string;
  to: string;
  total: number;
  points: OnboardingPoint[];
}
