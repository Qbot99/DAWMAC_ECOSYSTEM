import { useState } from "react";

interface LoginFormProps {
  setIsLoggedIn: (loggedIn: boolean) => void;
}

function LoginForm({ setIsLoggedIn }: LoginFormProps) {
  const [username, setUsername] = useState<string>("");
  const [password, setPassword] = useState<string>("");
  const [error, setError] = useState<string>("");

  const handleLogin = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError("");

    try {
      const res = await fetch(import.meta.env.VITE_DOMAIN + "/api/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ username, password }),
      });

      const data: { success: boolean; message?: string } = await res.json();

      if (data.success) {
        setIsLoggedIn(true);
      } else {
        setError(data.message || "Błąd logowania");
      }
    } catch (err) {
      console.error("Błąd zapytania:", err);
      setError("Błąd połączenia z serwerem");
    }
  };

  return (
    <form onSubmit={handleLogin}>
      <input
        value={username}
        onChange={(e) => setUsername(e.target.value)}
        placeholder="Login"
      />
      <input
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        type="password"
        placeholder="Hasło"
      />
      <button type="submit">Zaloguj</button>
      {error && <p style={{ color: "red" }}>{error}</p>}
    </form>
  );
}

export default LoginForm;
