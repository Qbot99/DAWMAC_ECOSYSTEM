import { useEffect, useState } from "react";

const AuthPanel = () => {
  const [authenticated, setAuthenticated] = useState(false);
  const [carBrandList, setCarBrandList] = useState<
    { id: number; name: string }[]
  >([]);
  const [carModelList, setCarModelList] = useState<
    { id: number; name: string }[]
  >([]);
  const [carBrand, setCarBrand] = useState<string | null>(null);
  const [galleryList, setGalleryList] = useState<
    { project_id: number; brand: string; model: string; image: string }[]
  >([]);
  const [galleryDel, setGalleryDel] = useState<string | null>(null);
  const [galleryPrim, setGalleryPrim] = useState<string | null>(null);
  const [imageList, setImageList] = useState<
    { id: string; image_url: string; is_primary: number }[]
  >([]);
  const [wheelBrand, setWheelBrand] = useState<string>("");
  const [wheelModel, setWheelModel] = useState<string>("");

  useEffect(() => {
    console.log(`https://${import.meta.env.PUBLIC_DOMAIN}/api/auth.php`);
    fetch(`https://${import.meta.env.PUBLIC_DOMAIN}/api/auth.php`)
      .then((res) => res.json())
      .then((data) => {
        if (!data.authenticated) {
          window.location.href = "/login";
        } else {
          setAuthenticated(true);
        }
      });
  }, []);

  const handleLogout = () => {
    fetch(`https://${import.meta.env.PUBLIC_DOMAIN}/api/logout.php`).then(
      () => (window.location.href = "/login")
    );
  };

  useEffect(() => {
    const fetchCarBrands = async () => {
      try {
        const res = await fetch(
          `https://${import.meta.env.PUBLIC_DOMAIN}/api/get_car_brands.php`
        );
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);

        const data = await res.json();
        setCarBrandList(data);
      } catch (error) {
        console.error("Error fetching car brands:", error);
      }
    };

    fetchCarBrands();
  }, []);

  useEffect(() => {
    const fetchCarModels = async () => {
      if (carBrand) {
        try {
          const res = await fetch(
            `https://${
              import.meta.env.PUBLIC_DOMAIN
            }/api/get_car_models.php?car_brand_id=${carBrand}`
          );
          if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);

          const data = await res.json();
          setCarModelList(data); // Now storing full objects (id, name)
        } catch (error) {
          console.error("Error fetching car models:", error);
        }
      }
    };

    fetchCarModels();
  }, [carBrand]);

  useEffect(() => {
    const fetchGalleries = async () => {
      try {
        const res = await fetch(
          `https://${import.meta.env.PUBLIC_DOMAIN}/api/get_projects.php`
        );
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);

        const data = await res.json();
        setGalleryList(data);
      } catch (error) {
        console.error("Error fetching galleries:", error);
      }
    };

    fetchGalleries();
  }, []);
  useEffect(() => {
    if (galleryPrim) {
      fetch(
        `https://${
          import.meta.env.PUBLIC_DOMAIN
        }/api/get_images.php?project_id=${galleryPrim}`
      )
        .then((res) => res.json())
        .then((data) => setImageList(data));
    }
  }, [galleryPrim]);

  return authenticated ? (
    <div>
      <h1>Panel Admina</h1>
      <button onClick={handleLogout}>Wyloguj</button>
      <div id="add-gallery">
        <h2>Dodaj galerię</h2>
        <form
          action="/api/add_gallery.php"
          method="POST"
          encType="multipart/form-data"
        >
          <h4>Samochód</h4>
          <div>
            <select
              name="car_brand"
              onChange={(e) => {
                setCarBrand(e.target.value);
              }}
            >
              <option value="">Wybierz markę samochodu</option>
              {carBrandList.map((brand) => (
                <option key={brand.id} value={brand.id}>
                  {brand.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <select name="car_model">
              <option value="">Wybierz model samochodu</option>
              {carModelList.map((model) => (
                <option key={model.id} value={model.id}>
                  {model.name}
                </option>
              ))}
            </select>
          </div>
          <h4>Felga</h4>
          <input
            type="text"
            name="wheel_brand"
            placeholder="Marka felgi"
            value={wheelBrand}
            onChange={(e) => setWheelBrand(e.target.value)}
          />
          <input
            type="text"
            name="wheel_model"
            placeholder="Model felgi"
            value={wheelModel}
            onChange={(e) => setWheelModel(e.target.value)}
          />
          <input
            type="text"
            name="wheel_params"
            placeholder="Parametry felgi"
          />

          {wheelBrand && wheelModel && (
            <div>
              <p>
                Sprawdź felgę w sklepie:{" "}
                <a
                  href={`https://dawmacpolska.pl/felgi-aluminiowe/producer-${wheelBrand
                    .toLowerCase()
                    .replace(/\s+/g, "-")}/model-${wheelModel
                    .toLowerCase()
                    .replace(/\s+/g, "-")}/`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Kliknij tutaj
                </a>
              </p>
              <p>
                Sklep nie pokazuje felg? Zaznacz poniższe pole, aby ukryć link w
                galeri.
                <input type="checkbox" name="dont_show_in_store" id="" />
              </p>
            </div>
          )}

          <h4>Zdjęcia</h4>
          <input type="file" name="images[]" multiple />

          <button type="submit">Wyślij</button>
        </form>
      </div>
      <div id="delete_gallery">
        <h2>Usuń galerię</h2>
        <form
          action="/api/delete_gallery.php"
          method="POST"
          encType="multipart/form-data"
        >
          <div>
            <select
              name="gallery_id"
              onChange={(e) => {
                setGalleryDel(e.target.value);
              }}
            >
              <option value="">Wybierz galerię</option>
              {galleryList.map((gallery) => (
                <option key={gallery.project_id} value={gallery.project_id}>
                  ID:{gallery.project_id} {gallery.brand} {gallery.model}
                </option>
              ))}
            </select>
            <br />
            {/* Find the selected gallery object */}
            {galleryDel &&
              (() => {
                const selectedGallery = galleryList.find(
                  (g) => g.project_id.toString() === galleryDel
                );
                return selectedGallery?.image ? (
                  <img
                    src={`https://${import.meta.env.PUBLIC_DOMAIN}/${
                      selectedGallery.image
                    }`}
                    alt=""
                    width={200}
                  />
                ) : null;
              })()}
          </div>
          <button type="submit">Usuń</button>
        </form>
      </div>
      <div id="set_primary_img">
        <h2>Ustaw zdjęcie główne</h2>
        <form
          action="/api/set_primary_image.php"
          method="POST"
          encType="multipart/form-data"
        >
          <div>
            <select
              name="gallery_id"
              onChange={(e) => {
                setGalleryPrim(e.target.value);
              }}
            >
              <option value="">Wybierz galerię</option>
              {galleryList.map((gallery) => (
                <option key={gallery.project_id} value={gallery.project_id}>
                  ID:{gallery.project_id} {gallery.brand} {gallery.model}
                </option>
              ))}
            </select>
            <br />
            {/* Find the selected gallery object */}
            {galleryPrim && (
              <div style={{ display: "flex", gap: "10px" }}>
                {imageList.map((img) => (
                  <div key={img.image_url}>
                    <input
                      type="radio"
                      name="primary_image"
                      value={img.image_url}
                      defaultChecked={img.is_primary === 1}
                      id={img.id}
                    />
                    <label htmlFor={img.id}>
                      <img
                        src={`https://${
                          import.meta.env.PUBLIC_DOMAIN
                        }/${img.image_url.replace(
                          "images/",
                          "images/thumb700_"
                        )}`}
                        alt=""
                        width={200}
                      />
                    </label>
                  </div>
                ))}
              </div>
            )}
          </div>
          <button type="submit">ustaw zdjęcie jako główne</button>
        </form>
      </div>
    </div>
  ) : null;
};

export default AuthPanel;
