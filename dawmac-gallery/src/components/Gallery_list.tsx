import { useEffect, useState } from "react";
import styles from "./Gallery_list.module.css";
import Gallery from "./Gallery";
import Filters from "./Filters";

interface Project {
  project_id: number;
  image: string;
  brand: string;
  model: string;
}

const fetchProjects = async (
  carBrand: string | null,
  carModel: string | null,
  searchKeyword: string | null
): Promise<Project[]> => {
  try {
    const queryParams = new URLSearchParams();
    if (carBrand) queryParams.append("car_brand_id", carBrand);
    // tu wyciągnij z url
    if (carModel) queryParams.append("car_model_id", carModel);
    if (searchKeyword) queryParams.append("search_keyword", searchKeyword);

    const url = `https://${
      import.meta.env.PUBLIC_DOMAIN
    }/api/get_projects.php?${queryParams.toString()}`;

    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`HTTP Error: ${res.status}`);
    }
    return await res.json();
  } catch (error) {
    console.error("Error fetching projects:", error);
    return [];
  }
};

const Gallery_list = () => {
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(
    null
  );
  const [carBrand, setCarBrand] = useState<string | null>(null);
  const [carModel, setCarModel] = useState<string | null>(null);
  const k = new URLSearchParams(window.location.search).get("Search");
  const [searchKeyword, setSearchKeyword] = useState<string | null>(k);
  const [isParamsLoaded, setIsParamsLoaded] = useState(false);
  const [imageErrors, setImageErrors] = useState<Record<number, boolean>>({});

  useEffect(() => {
    if (!isParamsLoaded) return;

    const loadProjects = async () => {
      const data = await fetchProjects(carBrand, carModel, searchKeyword);
      setProjects(data);
    };
    loadProjects();
  }, [carBrand, carModel, searchKeyword, isParamsLoaded]);

  const handleItemClick = (projectId: number) => {
    setSelectedProjectId(projectId);

    const params = new URLSearchParams(window.location.search);
    params.set("id", projectId.toString()); // dodaj lub zaktualizuj parametr id

    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.pushState({}, "", newUrl);
  };

  const updateProjectFromURL = () => {
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id");
    const car_brand = params.get("CarBrand");
    const car_model = params.get("CarModel");
    const keyword = params.get("Search");

    setSelectedProjectId(id ? Number(id) : null);
    setCarBrand(car_brand || null);
    setCarModel(car_model || null);
    setSearchKeyword(keyword);
    setIsParamsLoaded(true);
  };

  useEffect(() => {
    updateProjectFromURL(); // Pobranie ID przy pierwszym renderze

    const handlePopState = () => {
      updateProjectFromURL();
    };

    window.addEventListener("popstate", handlePopState);

    return () => {
      window.removeEventListener("popstate", handlePopState);
    };
  }, []);

  const handleImageError = (id: number) => {
    setImageErrors((prev) => ({ ...prev, [id]: true }));
  };
  return (
    <div id={styles.gallery_list_wrapper}>
      {selectedProjectId !== null && (
        <Gallery
          projectId={selectedProjectId}
          setSelectedProjectId={setSelectedProjectId}
        />
      )}
      <Filters
        carBrand={carBrand}
        carModel={carModel}
        searchKeyword={searchKeyword}
        setCarBrand={setCarBrand}
        setCarModel={setCarModel}
        setSearchKeyword={setSearchKeyword}
      />

      <div className={styles.galleryList}>
        {projects.map(({ project_id, image, brand, model }) =>
          !imageErrors[project_id] ? (
            <div
              key={project_id}
              className={styles.galleryListItem}
              onClick={() => handleItemClick(project_id)}
            >
              <img
                src={`https://${
                  import.meta.env.PUBLIC_API_DOMAIN
                }/gallery/${image.replace(
                  `images/${project_id}/`,
                  `images/${project_id}/thumb700_`
                )}`}
                // src={`https://${import.meta.env.PUBLIC_DOMAIN}/${image.replace(
                //   "images/",
                //   "images/thumb700_"
                // )}`}
                height={700}
                width={700}
                loading="lazy"
                onError={() => handleImageError(project_id)}
              />
              <span>
                {brand} {model}
              </span>
            </div>
          ) : null
        )}
      </div>
    </div>
  );
};

export default Gallery_list;
