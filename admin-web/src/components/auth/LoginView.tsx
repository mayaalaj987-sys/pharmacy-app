import { motion } from "framer-motion";
import { Eye, EyeOff, Mail, Lock, ShieldCheck, Loader2, AlertCircle } from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";
import authBg from "@/assets/pharmacy-bg.jpg";
import logo from "@/assets/logo.jpg";
import { useAuth } from "@/context/AuthContext";
import { AdminApiError } from "@/lib/adminApi";

function errorMessage(error: unknown): string {
  if (error instanceof AdminApiError) {
    switch (error.code) {
      case "invalid_credentials":
        return "That email and password don't match an active administrator account.";
      case "too_many_attempts":
        return "Too many attempts. Please wait a minute before trying again.";
      case "csrf_token_mismatch":
        return "Your session expired before submitting. Please try again.";
      case "origin_not_allowed":
        return "This origin isn't permitted to reach the admin API. Contact an administrator.";
      case "validation_failed":
        return "Enter a valid email and password.";
      case "network_error":
        return "Could not reach the server. Check your connection and try again.";
      default:
        return error.message || "Something went wrong. Please try again.";
    }
  }
  return "Something went wrong. Please try again.";
}

export function LoginView() {
  return (
    <div
      className="min-h-screen w-full flex relative overflow-hidden"
      style={{ background: "#0b221e" }}
    >
      <div className="hidden lg:block absolute inset-y-0 left-0 w-[52%] z-0 overflow-hidden">
        <img
          src={authBg}
          alt=""
          className="absolute inset-0 w-full h-full object-cover scale-105"
          style={{ filter: "blur(1px) saturate(1.05)" }}
        />
        <div className="absolute inset-0 bg-gradient-to-br from-emerald-900/55 via-emerald-950/50 to-[#0b221e]/70" />
        <div
          className="absolute inset-0"
          style={{
            background:
              "radial-gradient(ellipse at 30% 40%, rgba(16,185,129,0.15), transparent 65%)",
          }}
        />
        <div className="absolute inset-0 bg-[#0b221e]/20" />

        <div className="relative z-10 h-full flex flex-col justify-between p-12 text-white max-w-lg">
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 grid place-items-center">
              <img src={logo} alt="Smart Pharmacy" className="w-8 h-8 rounded-lg" />
            </div>
            <div>
              <p className="text-lg font-semibold tracking-tight">Smart Pharmacy</p>
              <p className="text-xs text-emerald-200/80 label-caption">Admin Console</p>
            </div>
          </div>

          <div
            className="flex items-center gap-6 text-xs text-white/75"
            style={{ textShadow: "0 1px 8px rgba(0,0,0,0.5)" }}
          >
            <span className="inline-flex items-center gap-2">
              <ShieldCheck size={14} /> Session-based access
            </span>
            <span>© 2026 Smart Pharmacy</span>
          </div>
        </div>
      </div>

      <div
        className="flex-1 flex items-center justify-center p-6 lg:p-12 relative z-10 lg:ml-[48%]"
        style={{ background: "#0f2b26" }}
      >
        <div
          className="absolute inset-0 opacity-60 pointer-events-none"
          style={{
            background:
              "radial-gradient(ellipse at 50% 30%, rgba(16,185,129,0.10), transparent 60%)",
          }}
        />

        <div className="w-full max-w-md relative z-10">
          <LoginForm />
        </div>

        <p className="absolute bottom-6 left-0 right-0 text-center text-xs text-emerald-200/50">
          🔒 Restricted access — authorized administrators only
        </p>
      </div>
    </div>
  );
}

function LoginForm() {
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [pwd, setPwd] = useState("");
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const valid = useMemo(() => /\S+@\S+\.\S+/.test(email) && pwd.length > 0, [email, pwd]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!valid || loading) return;
    setErr(null);
    setLoading(true);
    try {
      await login(email, pwd);
    } catch (error) {
      setErr(errorMessage(error));
    } finally {
      setLoading(false);
    }
  };

  return (
    <motion.form
      onSubmit={submit}
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
      className="space-y-6"
      noValidate
    >
      <div className="text-center">
        <h1 className="text-4xl font-bold tracking-tight text-white">Welcome back</h1>
        <p className="text-sm text-emerald-200/70 mt-2">Sign in to your administrator account</p>
      </div>

      <Field icon={<Mail size={16} strokeWidth={1.75} />} label="Work email" htmlFor="login-email">
        <input
          id="login-email"
          type="email"
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full bg-transparent outline-none text-sm text-emerald-50 placeholder:text-emerald-200/40"
          placeholder="you@company.com"
        />
      </Field>

      <Field icon={<Lock size={16} strokeWidth={1.75} />} label="Password" htmlFor="login-password">
        <input
          id="login-password"
          type={show ? "text" : "password"}
          autoComplete="current-password"
          value={pwd}
          onChange={(e) => setPwd(e.target.value)}
          className="w-full bg-transparent outline-none text-sm text-emerald-50 placeholder:text-emerald-200/40"
          placeholder="••••••••"
        />
        <button
          type="button"
          onClick={() => setShow((s) => !s)}
          aria-label={show ? "Hide password" : "Show password"}
          className="text-emerald-200/60 hover:text-emerald-100 transition"
        >
          {show ? <EyeOff size={16} strokeWidth={1.75} /> : <Eye size={16} strokeWidth={1.75} />}
        </button>
      </Field>

      {err && (
        <motion.p
          role="alert"
          initial={{ opacity: 0, y: -4 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-2 text-xs text-red-400"
        >
          <AlertCircle size={14} strokeWidth={1.75} /> {err}
        </motion.p>
      )}

      <motion.button
        type="submit"
        disabled={!valid || loading}
        whileTap={{ scale: 0.98 }}
        className="w-full h-12 rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400 text-emerald-950 font-semibold text-sm inline-flex items-center justify-center gap-2 transition-all disabled:opacity-70"
        style={{
          boxShadow: "0 0 30px rgba(16,185,129,0.45), 0 8px 24px -6px rgba(16,185,129,0.5)",
        }}
      >
        {loading ? (
          <>
            <Loader2 size={16} className="animate-spin" /> Logging in…
          </>
        ) : (
          "Log In"
        )}
      </motion.button>
    </motion.form>
  );
}

function Field({
  icon,
  label,
  htmlFor,
  children,
}: {
  icon: ReactNode;
  label: string;
  htmlFor: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label
        htmlFor={htmlFor}
        className="label-caption text-emerald-200/60 mb-1.5 block uppercase tracking-wider"
      >
        {label}
      </label>
      <div
        className="h-12 px-4 rounded-full flex items-center gap-3 transition-all border border-emerald-400/15 focus-within:border-emerald-400/50 focus-within:ring-2 focus-within:ring-emerald-400/20"
        style={{ background: "rgba(18,45,40,0.6)" }}
      >
        <span className="text-emerald-300/80">{icon}</span>
        {children}
      </div>
    </div>
  );
}
