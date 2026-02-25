import { useEffect, useState } from "react";
import { useLoading } from "./loading/LoadingContext";

type ProjectData = {
  project_id: number;
  brand: string;
  model: string;
  image: string;
  params: string;
};

// type DetailedProjectData = {
//   id: number;
//   brand: string;
//   model: string;
// };

export default function Gallery_edit_project() {
  const [projectList, setProjectList] = useState<ProjectData[]>([]);
  const [selectedProject, setSelectedProject] = useState<
    ProjectData | undefined
  >();
  const [selectedId, setSelectedId] = useState<string>("");
  // const [selectedProjectDetailed, setSelectedProjectDetailed] =
  //   useState<DetailedProjectData>();
  const [newBrand, setNewBrand] = useState<string>();
  const [newModel, setNewModel] = useState<string>();
  const [newParams, setNewParams] = useState<string>();
  const [imagesArray, setImagesArray] = useState<string[]>();
  const { setLoading } = useLoading();

  async function getProjects(): Promise<ProjectData[]> {
    try {
      const res = await fetch(
        import.meta.env.VITE_DOMAIN + "api/gallery/get_projects.php"
      );
      if (!res.ok) throw new Error("Błąd pobierania danych");
      return res.json();
    } catch (err) {
      console.error("Nie udało się pobrać listy felg:", err);
      return [];
    }
  }

  async function getProjectDetails(id: number) {
    try {
      const res = await fetch(
        import.meta.env.VITE_DOMAIN + "api/gallery/get_project_details.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded", // bo PHP to łatwo odbierze
          },
          body: `id=${id}`, // lub użyj URLSearchParams
        }
      );

      if (!res.ok) throw new Error("Błąd pobierania danych");
      return res.json();
    } catch (err) {
      console.error("Nie udało się pobrać szczegółów projektu:", err);
      return [];
    }
  }

  async function handleSelectChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const id = e.target.value;
    setSelectedId(id);

    const selected = projectList.find(
      (project) => project.project_id.toString() === id
    );
    setSelectedProject(selected);

    if (id) {
      const details = await getProjectDetails(Number(id));
      console.log(details);
      // setSelectedProjectDetailed(details);
      console.log("TEST" + details[0].params);
      setNewBrand(details[0].brand);
      setNewModel(details[0].model);
      setNewParams(details[0].params);
      setImagesArray(details[0].images);
    }
  }

  useEffect(() => {
    // Prawidłowe pobieranie danych asynchronicznych
    (async () => {
      const projects = await getProjects();
      setProjectList(projects);
    })();
  }, []);

  async function change_project_data() {
    if (!selectedProject) return;
    setLoading(true);
    const formData = new FormData();
    formData.append("project_id", selectedProject.project_id.toString());
    formData.append("brand", newBrand ?? "");
    formData.append("model", newModel ?? "");
    formData.append("params", newParams ?? "");

    const res = await fetch(
      import.meta.env.VITE_DOMAIN + "api/gallery/edit_project.php",
      {
        method: "POST",
        body: formData,
      }
    );

    if (!res.ok) throw new Error("Błąd aktualizacji danych");

    const response = await res.json();

    if (response.errors) {
      alert("Błąd przy edycji projektu.");
      setLoading(false);
    } else {
      alert("Projekt zaktualizowany.");
      setLoading(false);
      const updatedProjects = await getProjects();
      setProjectList(updatedProjects);
    }
  }

  async function delete_project() {
    setLoading(true);

    if (!selectedProject) return;

    const formData = new FormData();
    formData.append("gallery_id", selectedProject.project_id.toString());

    try {
      const res = await fetch(
        import.meta.env.VITE_DOMAIN + "api/gallery/delete_project.php",
        {
          method: "POST",
          body: formData,
        }
      );

      if (!res.ok) throw new Error("Błąd połączenia z API");

      const responseText = await res.text();

      try {
        const response = JSON.parse(responseText);
        if (response.errors) {
          console.error("Błędy usuwania:", response.errors);
          alert("Wystąpił błąd podczas usuwania galerii.");
          setLoading(false);
        } else {
          alert("Usunięto galerię.");
          setSelectedProject(undefined);
          setSelectedId("");
          const updatedProjects = await getProjects();
          setProjectList(updatedProjects);
          setLoading(false);
        }
      } catch {
        alert("Usunięto galerię (przekierowanie z serwera).");
        setSelectedProject(undefined);
        setSelectedId("");
        const updatedProjects = await getProjects();
        setProjectList(updatedProjects);
        setLoading(false);
      }
    } catch (err) {
      console.error("Błąd przy usuwaniu:", err);
      alert("Nie udało się usunąć galerii.");
      setLoading(false);
    }
  }

  async function delete_image(img: string) {
    const id = selectedId;
    console.log(id);
    console.log(img);

    const formData = new FormData();
    formData.append("id", id);
    formData.append("img", img);
    try {
      const res = await fetch(
        import.meta.env.VITE_DOMAIN + "api/gallery/delete_image.php",
        {
          method: "POST",
          body: formData,
        }
      );

      if (!res.ok) throw new Error("Błąd połączenia z API");

      const responseText = await res.text();

      try {
        const response = JSON.parse(responseText);
        if (response.errors) {
          console.error("Błędy usuwania:", response.errors);
          alert("Wystąpił błąd podczas usuwania galerii.");
          setLoading(false);
        } else {
          alert("Usunięto galerię.");
          setSelectedProject(undefined);
          setSelectedId("");
          const updatedProjects = await getProjects();
          setProjectList(updatedProjects);
          setLoading(false);
        }
      } catch {
        alert("Usunięto galerię (przekierowanie z serwera).");
        setSelectedProject(undefined);
        setSelectedId("");
        const updatedProjects = await getProjects();
        setProjectList(updatedProjects);
        setLoading(false);
      }
    } catch (err) {
      console.error("Błąd przy usuwaniu:", err);
      alert("Nie udało się usunąć galerii.");
      setLoading(false);
    }
  }

  return (
    <div id="gallery-edit-project">
      <h2>Edycja Galerii</h2>

      <select value={selectedId} onChange={handleSelectChange}>
        <option value="" disabled>
          Wybierz felgę
        </option>
        {projectList.map((project) => (
          <option key={project.project_id} value={project.project_id}>
            {project.project_id} - {project.brand} {project.model}
          </option>
        ))}
      </select>

      {selectedProject && (
        <div>
          <h3>Wybrany projekt:</h3>
          <p>ID: {selectedProject.project_id}</p>

          <label htmlFor="brand">Marka: </label>
          <input
            name="brand"
            type="text"
            value={newBrand}
            onChange={(e) => setNewBrand(e.target.value)}
          />
          <label htmlFor="model">Model: </label>
          <input
            name="model"
            type="text"
            value={newModel}
            onChange={(e) => setNewModel(e.target.value)}
          />
          <label htmlFor="params">Params: </label>
          <input
            name="params"
            type="text"
            value={newParams}
            onChange={(e) => setNewParams(e.target.value)}
          />
          <br />
          <div className="gallery-edit-project-images">
            {imagesArray?.map((img) => (
              <>
                <div className="gallery-edit-project-image-wrapper">
                  <button onClick={() => delete_image(img)}>Usuń</button>

                  <img
                    src={
                      import.meta.env.VITE_DOMAIN +
                      "/gallery/" +
                      img.replace(
                        `images/${selectedId}/`,
                        `images/${selectedId}/thumb700_`
                      )
                    }
                    className="gallery-edit-project-image"
                    alt={img}
                  />
                  <button>Ustaw jako główne</button>
                </div>
              </>
            ))}
          </div>
        </div>
      )}
      <button onClick={() => change_project_data()}>Zmień dane</button>

      <button onClick={() => delete_project()}>Usuń galerie</button>
    </div>
  );
}
