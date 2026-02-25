import { useState, useEffect } from "react";
import { useLoading } from "./loading/LoadingContext";

type CarBrand = {
  id: number;
  name: string;
};

type CarModel = {
  id: number;
  name: string;
  brand_id: number;
};

type Project = {
  id: number | null;
  wheel_brand: string;
  wheel_model: string;
  wheel_params: string;
  car_brand_id: number;
  car_model_id: number;
};

export default function Gallery_add_wheel() {
  const [carBrandList, setCarBrandList] = useState<CarBrand[]>([]);
  const [carModelList, setCarModelList] = useState<CarModel[]>([]);
  const [selectedCarBrand, setSelectedCarBrand] = useState<string>("");
  const [project, setProject] = useState<Project>({
    id: null,
    wheel_brand: "",
    wheel_model: "",
    wheel_params: "",
    car_brand_id: 0,
    car_model_id: 0,
  });
  const { setLoading } = useLoading();

  useEffect(() => {
    const fetchCarBrands = async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_DOMAIN}api/gallery/get_car_brands.php`
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
      try {
        const res = await fetch(
          `${
            import.meta.env.VITE_DOMAIN
          }api/gallery/get_car_models.php?car_brand_id=${selectedCarBrand}`
        );
        if (!res.ok)
          throw new Error(`HTTP error! Status: ${res.status}
          `);
        const data = await res.json();
        setCarModelList(data);
      } catch (error) {
        console.error("Error fetching car models:", error);
      }
    };
    fetchCarModels();
  }, [selectedCarBrand]);

  function addWheels(project: Project) {
    setLoading(true);
    console.log(project);

    const input = document.getElementById(
      "add-wheel-images"
    ) as HTMLInputElement;
    const files = input?.files;

    if (!files || files.length === 0) {
      console.log("Nie wybrano żadnych plików.");
      setLoading(false);
      return;
    }

    const formData = new FormData();
    formData.append("car_brand_id", String(project.car_brand_id));
    formData.append("car_model_id", String(project.car_model_id));
    formData.append("wheel_brand", project.wheel_brand ?? "");
    formData.append("wheel_model", project.wheel_model ?? "");
    formData.append("wheel_params", project.wheel_params ?? "");
    for (let i = 0; i < files.length; i++) {
      formData.append("images[]", files[i]);
    }

    fetch(import.meta.env.VITE_DOMAIN + "api/gallery/add_wheel.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => {
        console.log("Dodano felgę:", project);
        setLoading(false);
        return res.json();
      })
      .then((data) => {
        console.log("Response:", data);
      })
      .catch((err) => {
        console.log("Nie udało się dodać felgi:", project);
        console.error("Error:", err);
        setLoading(false);
      });
  }

  return (
    <>
      <div id="gallery-add-wheel">
        <h2>Dodaj felgę</h2>

        <select
          name="car_brand"
          id="car_brand"
          onChange={(e) => {
            setSelectedCarBrand(e.target.value);
            setProject({
              ...project,
              car_brand_id: Number(e.target.value),
            });
          }}
        >
          <option value="">Wybierz markę samochodu</option>
          {carBrandList.map((brand) => (
            <option key={brand.id} value={brand.id}>
              {brand.name}
            </option>
          ))}
        </select>
        <select
          name="car_model"
          id="car_model"
          onChange={(e) =>
            setProject({
              ...project,
              car_model_id: Number(e.target.value),
            })
          }
        >
          <option value="">Wybierz model samochodu</option>
          {carModelList.map((model) => (
            <option key={model.id} value={model.id}>
              {model.name}
            </option>
          ))}
        </select>
        <input
          type="text"
          placeholder="Marka felgi"
          onChange={(e) =>
            setProject({
              ...project,
              wheel_brand: e.target.value,
            })
          }
        />
        <input
          type="text"
          placeholder="Model felgi"
          onChange={(e) =>
            setProject({
              ...project,
              wheel_model: e.target.value,
            })
          }
        />
        <input
          type="text"
          placeholder="Parametry felgi"
          onChange={(e) =>
            setProject({
              ...project,
              wheel_params: e.target.value,
            })
          }
        />
        <input
          type="file"
          name="add-wheel-images[]"
          id="add-wheel-images"
          multiple
        />
        <button onClick={() => addWheels(project)}>Dodaj</button>
      </div>
    </>
  );
}
