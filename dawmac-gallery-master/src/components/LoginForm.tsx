import { useState } from "react";

const LoginForm = () => {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");

  const handleLogin = async () => {
    const res = await fetch(
      `https://${import.meta.env.PUBLIC_DOMAIN}/api/login.php`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password }),
      }
    );
    const data = await res.json();
    console.log(data);

    if (data.success) {
      window.location.href = "/adminPanel";
    } else {
      alert("Błędne dane logowania");
    }
  };

  return (
    <div>
      <h1>Logowanie</h1>
      <input
        type="text"
        placeholder="Login"
        value={username}
        onChange={(e) => setUsername(e.target.value)}
      />
      <input
        type="password"
        placeholder="Hasło"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
      />
      <button onClick={handleLogin}>Zaloguj</button>
    </div>
  );
};

export default LoginForm;
