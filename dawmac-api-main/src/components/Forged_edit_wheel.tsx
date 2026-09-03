import { useEffect, useRef, useState } from "react";
import { useLoading } from "./loading/LoadingContext";
import { FORGED_WHEELS_CHANGED } from "./Forged_add_wheel";

type WheelData = {
  id: number;
  name: string;
  description: string | null;
  series_name: string;
  images: string[];
};

export default function Forged_edit_wheel() {
  const [wheelList, setWheelList] = useState<WheelData[]>([]);
  const [selectedWheel, setSelectedWheel] = useState<WheelData | undefined>();
  const [selectedId, setSelectedId] = useState<string>("");
  const [newWheelName, setNewWheelName] = useState<string>("");
  const [newDescription, setNewDescription] = useState<string>("");
  const { setLoading } = useLoading();

  const fileInputRef = useRef<HTMLInputElement | null>(null);

  async function getWheels(): Promise<WheelData[]> {
    try {
      const res = await fetch(
        import.meta.env.VITE_DOMAIN + "api/forged/list_wheels.php"
      );
      if (!res.ok) throw new Error("Błąd pobierania danych");
      return res.json();
    } catch (err) {
      console.error("Nie udało się pobrać listy felg:", err);
      return [];
    }
  }

  useEffect(() => {
    refreshWheelList();
    // Po dodaniu felgi w sąsiednim formularzu lista ma się odświeżyć.
    window.addEventListener(FORGED_WHEELS_CHANGED, refreshWheelList);
    return () =>
      window.removeEventListener(FORGED_WHEELS_CHANGED, refreshWheelList);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedId]);

  function refreshWheelList() {
    getWheels().then((data) => {
      setWheelList(data);
      const selected = data.find((wheel) => wheel.id.toString() === selectedId);
      setSelectedWheel(selected);
      setNewWheelName(selected?.name ?? "");
      setNewDescription(selected?.description ?? "");
    });
  }

  function handleSelectChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const id = e.target.value;
    setSelectedId(id);

    const selected = wheelList.find((wheel) => wheel.id.toString() === id);
    setSelectedWheel(selected);
    setNewWheelName(selected?.name ?? "");
    setNewDescription(selected?.description ?? "");
  }

  function deleteWheel(wheel_id: number) {
    setLoading(true);
    fetch(import.meta.env.VITE_DOMAIN + "api/forged/delete_wheel.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `wheel_id=${encodeURIComponent(wheel_id)}`,
    })
      .then((res) => res.json())
      .then((data) => {
        console.log("Response:", data);
        console.log("Usunięto felgę ID: " + wheel_id);

        refreshWheelList();
        setLoading(false);
      })
      .catch((err) => {
        console.error("Nie udało się usunąć felgi ID:", wheel_id, err);
        setLoading(false);
      });
  }

  function saveWheel(
    wheel_id: number,
    new_wheel_name: string,
    new_description: string
  ) {
    setLoading(true);

    fetch(import.meta.env.VITE_DOMAIN + "api/forged/edit_wheel_name.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        `wheel_id=${encodeURIComponent(wheel_id)}` +
        `&wheel_name=${encodeURIComponent(new_wheel_name.trim())}` +
        `&description=${encodeURIComponent(new_description.trim())}`,
    })
      .then((res) => {
        if (!res.ok) throw new Error("HTTP error " + res.status);
        return res.json();
      })
      .then((data) => {
        console.log("Response:", data);
        console.log("Zapisano felgę ID: " + wheel_id);
        refreshWheelList();
        window.dispatchEvent(new Event(FORGED_WHEELS_CHANGED));
        setLoading(false);
      })
      .catch((err) => {
        console.error("Nie udało się zapisać felgi ID:", wheel_id, err);
        setLoading(false);
      });
  }

  function deleteImage(wheel_id: number, img_url: string) {
    setLoading(true);

    fetch(import.meta.env.VITE_DOMAIN + "api/forged/delete_image.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `wheel_id=${encodeURIComponent(
        wheel_id
      )}&img_url=${encodeURIComponent(img_url)}`,
    })
      .then((res) => {
        res.json();
        setLoading(false);
      })
      .then((data) => {
        console.log("Response:", data);
        console.log("Usunięto zdjęcie ID felgi:", wheel_id, "URL:", img_url);
        refreshWheelList();
        setLoading(false);
      })
      .catch((err) => {
        console.error("Nie udało się usunąć zdjęcia ID:", wheel_id, err);
        setLoading(false);
      });
  }

  function setPrimaryImage(wheel_id: number, img_url: string) {
    setLoading(true);

    fetch(import.meta.env.VITE_DOMAIN + "api/forged/set_primary_image.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `wheel_id=${encodeURIComponent(
        wheel_id
      )}&img_url=${encodeURIComponent(img_url)}`,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data?.error) console.error("Zdjęcie główne:", data.error);
        refreshWheelList();
        window.dispatchEvent(new Event(FORGED_WHEELS_CHANGED));
        setLoading(false);
      })
      .catch((err) => {
        console.error("Nie udało się ustawić zdjęcia głównego:", err);
        setLoading(false);
      });
  }

  function addWheelImages(wheel_id: number, files: FileList | null) {
    setLoading(true);

    if (!files || files.length === 0) {
      console.log("Nie wybrano żadnych plików.");
      return;
    }

    const formData = new FormData();
    formData.append("wheel_id", wheel_id.toString());
    for (let i = 0; i < files.length; i++) {
      formData.append("images[]", files[i]);
    }

    fetch(import.meta.env.VITE_DOMAIN + "api/forged/add_wheel_images.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => {
        res.json();
        setLoading(false);
      })
      .then((data) => {
        console.log("Response:", data);
        console.log("Zdjęcia dodane pomyślnie.");
        refreshWheelList();
        setLoading(false);
      })
      .catch((err) => {
        console.error("Nie udało się dodać zdjęć:", err);
        setLoading(false);
      });
  }

  return (
    <div id="forged-edit-wheel">
      <h2>Edycja Felgi</h2>

      <select value={selectedId} onChange={handleSelectChange}>
        <option value="" disabled>
          Wybierz felgę
        </option>
        {wheelList.map((wheel) => (
          <option key={wheel.id} value={wheel.id}>
            {wheel.name}
          </option>
        ))}
      </select>

      {selectedWheel && (
        <>
          <div id="forged-edit-wheel-info">
            <span>ID: {selectedWheel.id}</span>
            <input
              type="text"
              value={newWheelName}
              onChange={(e) => setNewWheelName(e.target.value)}
            />
            <textarea
              placeholder="Opis felgi (opcjonalnie)"
              rows={3}
              value={newDescription}
              onChange={(e) => setNewDescription(e.target.value)}
            />

            <div id="forged-edit-wheel-images">
              {selectedWheel.images.map((img, i) => (
                <div
                  key={img}
                  className={
                    "forged-edit-image" +
                    (i === 0 ? " forged-edit-image--main" : "")
                  }
                >
                  <img
                    src={import.meta.env.VITE_DOMAIN + "forged/" + img}
                    width={100}
                  />
                  {i === 0 ? (
                    <span className="forged-edit-image__badge">Główne</span>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setPrimaryImage(selectedWheel.id, img)}
                    >
                      Ustaw główne
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => deleteImage(selectedWheel.id, img)}
                  >
                    Usuń
                  </button>
                </div>
              ))}
              <input
                ref={fileInputRef}
                type="file"
                name="add-wheel-images[]"
                multiple
              />
              <button
                onClick={() =>
                  addWheelImages(
                    selectedWheel.id,
                    fileInputRef.current?.files || null
                  )
                }
              >
                Dodaj
              </button>
            </div>
          </div>

          <div id="forged-edit-controlls">
            <button
              onClick={() =>
                saveWheel(selectedWheel.id, newWheelName, newDescription)
              }
            >
              Zapisz nazwę i opis
            </button>
            <button onClick={() => deleteWheel(selectedWheel.id)}>
              Usuń felgę
            </button>
          </div>
        </>
      )}
    </div>
  );
}
