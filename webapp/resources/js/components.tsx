import { useEffect, useRef, type ReactNode, type InputHTMLAttributes } from 'react';
import { X } from 'lucide-react';
import type { Money } from './types';

export const months = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
export const amount = (value: number) => `${value < 0 ? '−' : ''}${String(Math.abs(value)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')}`;
export const labels: Record<string,string> = { expense: 'Kiadás', income: 'Bevétel', transfer: 'Átvezetés', saving: 'Családi félretétel', saving_return: 'Megtakarítás visszavétele', child_saving: 'Örs megtakarítása' };
type MoneyInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'type' | 'inputMode'> & {
    value: string | number;
    onValueChange: (raw: string) => void;
    allowNegative?: boolean;
};
export function MoneyInput({ value, onValueChange, allowNegative = false, ...props }: MoneyInputProps) {
    const ref = useRef<HTMLInputElement>(null);
    const raw = String(value);
    const formatted = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const setValue = (text: string, digitsBeforeCaret: number, caretAfterSign = false) => {
        const negative = /^[-−]/.test(text);
        const digits = text.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        const next = (negative ? '-' : '') + digits;
        onValueChange(next);
        requestAnimationFrame(() => {
            const input = ref.current;
            if (!input || document.activeElement !== input) return;
            let index = caretAfterSign && negative ? 1 : 0;
            let count = 0;
            while (index < input.value.length && count < digitsBeforeCaret) {
                if (/\d/.test(input.value[index])) count++;
                index++;
            }
            input.setSelectionRange(index, index);
        });
    };
    return <input {...props} ref={ref} type="text" inputMode="numeric" pattern={allowNegative ? '-?[0-9 ]+' : '[0-9 ]+'} value={formatted}
        onChange={event => {
            const input = event.currentTarget;
            const caret = input.selectionStart ?? input.value.length;
            setValue(input.value, input.value.slice(0, caret).replace(/\D/g, '').length, caret > 0);
        }}
        onKeyDown={event => {
            props.onKeyDown?.(event);
            if (event.defaultPrevented || !['Backspace', 'Delete'].includes(event.key)) return;
            const input = event.currentTarget;
            const caret = input.selectionStart ?? 0;
            if (caret !== input.selectionEnd) return;
            const backward = event.key === 'Backspace';
            if (input.value[backward ? caret - 1 : caret] !== ' ') return;
            event.preventDefault();
            const before = input.value.slice(0, caret).replace(/\D/g, '').length;
            const digits = input.value.replace(/\D/g, '');
            const remove = backward ? before - 1 : before;
            const next = digits.slice(0, remove) + digits.slice(remove + 1);
            setValue((input.value.startsWith('-') ? '-' : '') + next, backward ? before - 1 : before, caret > 0);
        }}
    />;
}
export function Ft({ value, className = '' }: { value: number | Money; className?: string }) {
    return <span className={`money ${className}`} title={typeof value === 'number' ? undefined : `Pontos érték: ${value.exact} Ft`}>
        {typeof value === 'number' ? amount(value) : value.text.replace(/(,\d{2})\d+…$/, '$1…')}<span className="currency"> Ft</span>
    </span>;
}
export function ErrorText({ error }: {error?: string}) { return error ? <p className="field-error" role="alert">{error}</p> : null; }
export function Sheet({ title, close, children }: { title: string; close: () => void; children: ReactNode }) {
    const ref = useRef<HTMLDialogElement>(null);
    useEffect(() => {
        const oldOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const dialog = ref.current!;
        dialog.showModal();
        const cancel = (e: Event) => { e.preventDefault(); close(); };
        dialog.addEventListener('cancel', cancel);
        return () => { dialog.removeEventListener('cancel', cancel); dialog.close(); document.body.style.overflow = oldOverflow; };
    }, []);
    return <dialog ref={ref} className="sheet" aria-labelledby="sheet-title" onClick={e => { if (e.target === e.currentTarget) close(); }}>
        <div className="sheet-head"><div><div className="sheet-handle" /><h2 id="sheet-title">{title}</h2></div><button className="icon-button" aria-label="Bezárás" onClick={close}><X size={21}/></button></div>
        {children}
    </dialog>;
}
