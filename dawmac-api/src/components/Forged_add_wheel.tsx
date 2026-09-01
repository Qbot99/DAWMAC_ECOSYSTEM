export default function Forged_add_wheel() {
  return (
    <>
      <div id="forged-add-wheel">
        <h2>Dodaj felgę</h2>
        <form
          action="api/forged/add_wheel.php"
          method="POST"
          encType="multipart/form-data"
          id="forged-add-wheel-form"
        >
          <input type="text" name="wheel_name" placeholder="Nazwa felgi" />
          <div>
            <input
              type="radio"
              name="series"
              id="series_input_monoblock"
              value="1"
            />
            <label htmlFor="series_input_monoblock">Monoblock</label>

            <input
              type="radio"
              name="series"
              id="series_input_dwuczesciowe"
              value="2"
            />
            <label htmlFor="series_input_dwuczesciowe">Dwuczęściowe</label>
          </div>
          <input type="file" name="wheel_image[]" id="wheel_image" multiple />
          <button type="submit">Dodaj</button>
        </form>
      </div>
    </>
  );
}
