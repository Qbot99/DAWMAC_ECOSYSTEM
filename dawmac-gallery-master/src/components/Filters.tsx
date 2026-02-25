import React, { use, useEffect, useState } from "react";
import styles from "./Filters.module.css";
import { useDebouncedInput } from "../hooks/useDebouncedInput";

interface FiltersProps {
  carBrand: string | null;
  carModel: string | null;
  searchKeyword: string | null;
  setCarBrand: React.Dispatch<React.SetStateAction<string | null>>;
  setCarModel: React.Dispatch<React.SetStateAction<string | null>>;
  setSearchKeyword: React.Dispatch<React.SetStateAction<string | null>>;
}

function Filters({
  carBrand,
  carModel,
  searchKeyword,
  setCarBrand,
  setCarModel,
  setSearchKeyword,
}: FiltersProps) {
  const [carBrandList, setCarBrandList] = useState<
    { id: number; name: string }[]
  >([]);
  const [carModelList, setCarModelList] = useState<
    { id: number; name: string }[]
  >([]);

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

  const input = useDebouncedInput(searchKeyword ?? "", 500, (val) => {
    if (val === searchKeyword) return;

    const params = new URLSearchParams(window.location.search);

    setSearchKeyword(val);

    params.set("Search", val);
    window.history.pushState(
      {},
      "",
      `${window.location.pathname}?${params.toString()}`
    );
  });


  return (
    <div id={styles.gallery_list_filters}>
      <div id={styles.car_brand_filter_wrapper}>
        <select
          id={styles.car_brand_filter}
          value={carBrand ?? ""}
          onChange={(e) => {
            setCarBrand(e.target.value);

            const params = new URLSearchParams(window.location.search);
            params.set("CarBrand", e.target.value);
            params.set("CarModel", "");
            window.history.pushState(
              {},
              "",
              `${window.location.pathname}?${params.toString()}`
            );

            setCarModel(null);
          }}
        >
          <option value="">Wybierz markę samochodu</option>
          {carBrandList.map((brand) => (
            <option key={brand.id} value={brand.id.toString()}>
              {brand.name}
            </option>
          ))}
        </select>
      </div>
      <div id={styles.car_model_filter_wrapper}>
        <select
          id={styles.car_model_filter}
          value={carModel ?? ""}
          onChange={(e) => {
            setCarModel(e.target.value);

            const params = new URLSearchParams(window.location.search);

            params.set("CarModel", e.target.value);
            window.history.pushState(
              {},
              "",
              `${window.location.pathname}?${params.toString()}`
            );
          }}
        >
          <option value="">Wybierz model samochodu</option>
          {carBrand &&
            carModelList.map((model) => (
              <option key={model.id} value={model.id}>
                {model.name}
              </option>
            ))}
        </select>
      </div>
      <input
        type="text"
        placeholder="Wyszukaj felgi"
        id={styles.car_model_filter_wrapper}
        value={input.value}
        onChange={input.onChange}
      />
    </div>
  );
}

export default Filters;
