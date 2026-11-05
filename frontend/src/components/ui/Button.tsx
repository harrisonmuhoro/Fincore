import type { ButtonHTMLAttributes } from 'react'
import { cn } from '../../lib/utils'

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'destructive' | 'ghost' }

export function Button({ className, variant = 'primary', ...props }: ButtonProps) {
  const variants = {
    primary: 'bg-[var(--color-brand-950)] text-white hover:bg-[var(--color-brand-800)]',
    secondary: 'border border-[var(--color-border)] bg-white text-[var(--color-ink)] hover:bg-slate-50',
    destructive: 'bg-[var(--color-danger)] text-white hover:bg-red-800',
    ghost: 'text-[var(--color-ink)] hover:bg-slate-100',
  }
  return <button className={cn('inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition-colors disabled:pointer-events-none disabled:opacity-50', variants[variant], className)} {...props} />
}
