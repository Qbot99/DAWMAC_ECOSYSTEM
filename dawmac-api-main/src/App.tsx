import { useState, useEffect } from "react";
import "./App.css";
import LoginForm from "./components/login";
import Forged_add_wheel from "./components/Forged_add_wheel";
import Gallery_add_wheel from "./components/Gallery_add_wheel";
import Gallery_fix_list from "./components/Gallery_fix_list";
import Gallery_edit_wheel from "./components/Gallery_edit_wheel";
import Forged_edit_wheel from "./components/Forged_edit_wheel";
import LoadingProvider from "./components/loading/LoadingProvider";
function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    async function checkLogin() {
      try {
        const res = await fetch(
          import.meta.env.VITE_DOMAIN + "/api/check_login.php",
          {
            credentials: "include",
          }
        );
        const data = await res.json();
        setIsLoggedIn(data.loggedIn);
      } catch (err) {
        console.error("Błąd sprawdzania logowania:", err);
      } finally {
        setChecking(false);
      }
    }
    checkLogin();
  }, []);

  const handleLogout = async () => {
    await fetch(import.meta.env.VITE_DOMAIN + "/api/logout.php", {
      method: "POST",
      credentials: "include",
    });
    setIsLoggedIn(false);
  };

  if (checking) {
    return <p>Sprawdzam czy jesteś zalogowany...</p>;
  }

  return (
    <>
      <h1>Admin Panel</h1>
      {isLoggedIn ? (
        <>
          <LoadingProvider>
            <button onClick={handleLogout}>Wyloguj się</button>
            <h2>
              <a href="https:/galeria.dawmacpolska.pl">
                galeria.dawmacpolska.pl
              </a>
            </h2>

            <Gallery_add_wheel />
            <Gallery_fix_list />
            <Gallery_edit_wheel />
            <hr />
            <div id="forged">
              <h2>
                <a href="https:/forged.dawmacpolska.pl">
                  forged.dawmacpolska.pl
                </a>
              </h2>
              <div id="forged-tools">
                <Forged_add_wheel />
                <Forged_edit_wheel />
              </div>
            </div>
          </LoadingProvider>
        </>
      ) : (
        <LoginForm setIsLoggedIn={setIsLoggedIn} />
      )}
    </>
  );
}
export default App;
