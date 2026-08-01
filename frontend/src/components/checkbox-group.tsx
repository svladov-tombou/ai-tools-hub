"use client";

export type CheckboxOption = { id: number; label: string };

type BulkActions = { selectAllLabel: string; clearAllLabel: string };

type CheckboxGroupProps = {
  heading: string;
  options: CheckboxOption[];
  selectedIds: number[];
  onChange: (ids: number[]) => void;
  columns?: 2 | 3;
  hint?: string;
  bulkActions?: BulkActions;
  disabled?: boolean;
};

const COLUMN_CLASSES: Record<2 | 3, string> = {
  2: "grid-cols-1 sm:grid-cols-2",
  3: "grid-cols-2 sm:grid-cols-3",
};

export function CheckboxGroup({
  heading,
  options,
  selectedIds,
  onChange,
  columns = 3,
  hint,
  bulkActions,
  disabled,
}: CheckboxGroupProps) {
  function toggle(id: number) {
    if (selectedIds.includes(id)) {
      onChange(selectedIds.filter((selectedId) => selectedId !== id));
    } else {
      onChange([...selectedIds, id]);
    }
  }

  function selectAll() {
    onChange(options.map((option) => option.id));
  }

  function clearAll() {
    onChange([]);
  }

  return (
    <fieldset className="rounded-md border border-border p-3">
      <legend className="px-1 text-sm font-medium text-text-primary">{heading}</legend>
      {bulkActions ? (
        <div className="mb-2 flex gap-3">
          <button
            type="button"
            className="text-xs text-accent hover:underline"
            onClick={selectAll}
            disabled={disabled}
          >
            {bulkActions.selectAllLabel}
          </button>
          <button
            type="button"
            className="text-xs text-accent hover:underline"
            onClick={clearAll}
            disabled={disabled}
          >
            {bulkActions.clearAllLabel}
          </button>
        </div>
      ) : null}
      {hint ? <p className="mb-2 text-xs text-text-secondary">{hint}</p> : null}
      <div className={`grid gap-x-4 gap-y-1 ${COLUMN_CLASSES[columns]}`}>
        {options.map((option) => (
          <label key={option.id} className="flex items-center gap-2 text-sm text-text-primary">
            <input
              type="checkbox"
              className="accent-accent"
              checked={selectedIds.includes(option.id)}
              onChange={() => toggle(option.id)}
              disabled={disabled}
            />
            {option.label}
          </label>
        ))}
      </div>
    </fieldset>
  );
}
