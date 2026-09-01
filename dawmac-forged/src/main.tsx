import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import { ToastProvider } from "./components/Toast";
import { ForgedDataProvider } from "./data/useForgedData";
import { LangProvider } from "./i18n";
import "./styles/forged.css";

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <LangProvider>
      <ForgedDataProvider>
        <ToastProvider>
          <App />
        </ToastProvider>
      </ForgedDataProvider>
    </LangProvider>
  </StrictMode>
);
