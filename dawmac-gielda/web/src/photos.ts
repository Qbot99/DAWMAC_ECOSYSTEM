// Zmniejszanie zdjęć w przeglądarce przed wysłaniem. Zdjęcie z telefonu
// ma 4–10 MB; po zmniejszeniu do 2000 px ma kilkaset KB, więc 8 zdjęć
// przechodzi przez limity hostingu i szybko wysyła się na komórce.
// Serwer i tak przetwarza je od nowa (WebP, usunięcie EXIF).

const MAX_EDGE = 2000

export async function shrinkPhoto(file: File): Promise<File> {
  if (!file.type.startsWith('image/')) return file
  try {
    const bmp = await createImageBitmap(file) // uwzględnia orientację z EXIF
    const scale = Math.min(1, MAX_EDGE / Math.max(bmp.width, bmp.height))
    if (scale === 1 && file.size < 1_500_000) {
      bmp.close()
      return file
    }
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bmp.width * scale)
    canvas.height = Math.round(bmp.height * scale)
    canvas.getContext('2d')!.drawImage(bmp, 0, 0, canvas.width, canvas.height)
    bmp.close()
    const blob = await new Promise<Blob | null>((r) => canvas.toBlob(r, 'image/jpeg', 0.88))
    if (!blob) return file
    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' })
  } catch {
    return file // np. HEIC w przeglądarce bez obsługi — serwer powie, co jest nie tak
  }
}
