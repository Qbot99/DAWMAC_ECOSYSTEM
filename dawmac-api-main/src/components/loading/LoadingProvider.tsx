// src/context/LoadingProvider.tsx
import React, { useState } from "react";
import { LoadingContext } from "./LoadingContext";
import LoadingOverlay from "./LoadingOverlay";

const LoadingProvider = ({ children }: { children: React.ReactNode }) => {
  const [loading, setLoading] = useState(false);

  return (
    <LoadingContext.Provider value={{ loading, setLoading }}>
      {loading && <LoadingOverlay />}
      {children}
    </LoadingContext.Provider>
  );
};

export default LoadingProvider;
