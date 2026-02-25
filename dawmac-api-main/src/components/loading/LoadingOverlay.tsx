// src/components/shared/LoadingOverlay.tsx
const LoadingOverlay = () => (
  <div
    style={{
      position: "fixed",
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      backgroundColor: "rgba(0,0,0,0.5)",
      color: "white",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      fontSize: "2rem",
      zIndex: 1000,
    }}
  >
    Ładowanie...
  </div>
);

export default LoadingOverlay;
