import { useEffect, useState } from "react";
import styles from "./Gallery.module.css";
import Carousel from "./Carousel";

interface GalleryProps {
  projectId: number;
  setSelectedProjectId: React.Dispatch<React.SetStateAction<number | null>>;
}

function deleteParameterFromUrl(param: string) {
  const params = new URLSearchParams(window.location.search);
  params.delete(param); // usuwa tylko parametr id

  const newUrl = `${window.location.pathname}?${params.toString()}`;
  window.history.pushState({}, "", newUrl);
}

const Gallery = ({ projectId, setSelectedProjectId }: GalleryProps) => {
  const [project, setProject] = useState<any>(null);
  const [storeLink, setStoreLink] = useState<string>("");

  useEffect(() => {
    const fetchProjectDetails = async () => {
      try {
        // window.history.pushState({}, "", `?id=${projectId}`);

        const res = await fetch(
          `https://${
            import.meta.env.PUBLIC_DOMAIN
          }/api/get_project_details.php?id=${projectId}`
        );
        if (res.ok) {
          const data = await res.json();
          if (data[0].brand && data[0].model) {
            setStoreLink(
              `https://dawmacpolska.pl/felgi-aluminiowe/producer-${data[0].brand
                .toLowerCase()
                .replace(/\s+/g, "-")}/model-${data[0].model
                .toLowerCase()
                .replace(/\s+/g, "-")}/`
            );
          } else if (
            data[0].brand.toLowerCase() == "Dawmac Forged".toLowerCase()
          ) {
            setStoreLink(`https://dawmacpolska.pl/dawmac-forged/`);
          } else {
            data[0].show_in_store = false;
          }
          setProject(data[0]);
          console.log(project);
        }
      } catch (error) {
        console.error("Error fetching project details:", error);
      }
    };
    fetchProjectDetails();
  }, [projectId]);

  if (!project) {
    return;
  }
  const imageUrl =
    typeof project.images === "string" ? project.images.split(", ") : "";
  console.log(imageUrl);

  const handleBgClick = () => {
    setSelectedProjectId(null);

    deleteParameterFromUrl("id");
  };

  return (
    <div className={styles.gallery} onClick={handleBgClick}>
      <h2>
        {project.brand} {project.model}
      </h2>
      <h3>{project.params}</h3>

      <div id={styles.carousel_wrapper}>
        <Carousel
          images={imageUrl}
          setSelectedProjectId={setSelectedProjectId}
        />
      </div>
    </div>
  );
};

export default Gallery;
