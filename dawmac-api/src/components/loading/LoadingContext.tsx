// src/context/LoadingContext.tsx
import { createContext, useContext } from "react";

export type LoadingContextType = {
  loading: boolean;
  setLoading: (val: boolean) => void;
};

export const LoadingContext = createContext<LoadingContextType | undefined>(
  undefined
);

export const useLoading = () => {
  const ctx = useContext(LoadingContext);
  if (!ctx) throw new Error("useLoading must be used within LoadingProvider");
  return ctx;
};
