import { useState, useEffect, type SetStateAction } from "react";

export function useDebouncedInput(initialValue :string, delay = 500, onDebouncedChange = (value: string) => {}) {
  const [value, setValue] = useState(initialValue);

  useEffect(() => {
    const handler = setTimeout(() => {
      onDebouncedChange(value);
    }, delay);

    return () => clearTimeout(handler);
  }, [value, delay]);

  return {
    value,
    onChange: (e: { target: { value: SetStateAction<string>; }; }) => setValue(e.target.value),
    setValue, // opcjonalnie, jeśli chcesz ręcznie zmieniać
  };
}
