import { useEffect, useRef, type DialogHTMLAttributes, type ReactNode } from 'react'

type DialogProps = Omit<DialogHTMLAttributes<HTMLDialogElement>, 'open' | 'onClose'> & {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
}

export function Dialog({ open, onClose, title, children, ...props }: DialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = dialogRef.current
    if (!dialog) return
    if (open && !dialog.open) dialog.showModal()
    if (!open && dialog.open) dialog.close()
  }, [open])

  return <dialog ref={dialogRef} aria-labelledby="dialog-title" onClose={onClose} className="max-h-[85vh] w-[min(92vw,36rem)] rounded-xl border border-[var(--color-border)] bg-white p-0 text-[var(--color-ink)] shadow-2xl backdrop:bg-slate-950/40" {...props}><div className="border-b border-[var(--color-border)] px-5 py-4"><h2 id="dialog-title" className="font-semibold">{title}</h2></div><div className="p-5">{children}</div></dialog>
}
