import { useEffect, useState } from "react";
import styles from "./ProjectGallery.module.css";

interface Props {
  wheel_name: string;
}

interface Project {
  project_id: string;
  brand: string;
  model: string;
  image: string;
}

export default function ProjectGallery({ wheel_name }: Props) {
  const [projects, setProjects] = useState<Project[]>([]);

  useEffect(() => {
    const fetchProjects = async () => {
      try {
        const res = await fetch(
          `https://galeria.dawmacpolska.pl/api/get_projects.php?search_keyword=Dawmac Forged ${wheel_name}%20`
        );
        const data = await res.json();
        setProjects(data);
      } catch (error) {
        console.error("Błąd podczas pobierania projektów:", error);
      }
    };

    fetchProjects();
  }, [wheel_name]);

  if (projects.length > 0) {
    return (
      <div>
        <h2 className={styles.title}>Galeria projektów</h2>
        <a
          key={projects[0].project_id}
          href={`https://galeria.dawmacpolska.pl/?Search=Dawmac+Forged+${projects[0].model}+`}
          target="_blank"
          rel="noopener noreferrer"
          className={styles.imageWrapper}
        >
          <img
            className={styles.galleryImage}
            src={`https://api.dawmacpolska.pl/gallery/${projects[0].image}`}
            alt={`${projects[0].brand} ${projects[0].model}`}
          />
          <div className={styles.button}>Zobacz więcej</div>
        </a>
      </div>
    );
  }

  return null;
}
