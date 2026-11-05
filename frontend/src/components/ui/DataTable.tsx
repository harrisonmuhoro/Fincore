import type { TableHTMLAttributes } from 'react'
import { cn } from '../../lib/utils'

export function DataTable({ className, ...props }: TableHTMLAttributes<HTMLTableElement>) {
  return <div className="w-full overflow-x-auto rounded-lg border border-[var(--color-border)]"><table className={cn('w-full border-collapse text-left text-sm', className)} {...props} /></div>
}
