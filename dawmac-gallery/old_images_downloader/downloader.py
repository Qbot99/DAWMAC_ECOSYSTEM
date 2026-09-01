import requests
from bs4 import BeautifulSoup
import re
import os

print("DAWMAC - Old Images Downloader")

car_brand = 'FORD1/FORD_2024'

def get_html(url):
    try:
        response = requests.get(url)
        print(f"Status Code: {response.status_code}")
        if response.status_code == 200:
            # return response.content
            print(response.content)
            soup = BeautifulSoup(response.content, 'html.parser')
            return soup
        else:
            print(f"Failed to fetch the page. Status Code: {response.status_code}")
            return None
    except requests.exceptions.RequestException as e:
        print(f"An error occurred: {e}")
        return None
    
def extract_images(soup):
    if soup is None:
        print("No HTML content to parse.")
        return

    images = soup.select('#ImagesData img')
    print(f"Found {len(images)} <img> elements:")
    for img in images:
        try:
            image_src = f"https://dawmac.eu{img['src']}".replace(",Medium", "")
            print(f"{image_src} \n {img['alt']}")

            folder_name = re.sub(r'[<>:"/\\|?*\x00-\x1F]', "_", img['alt'])
            folder_name = re.sub(r'\d{2}_\d{2}_\d{4} \d{2}_\d{2}\b', "", folder_name).strip().replace("__n__n", "")
            folder_location = f"./output/{car_brand.replace('"', "")}/{folder_name}"

            os.makedirs(folder_location, exist_ok=True)

            image_path = os.path.join(folder_location, image_src.split('/')[-1])
            with open(image_path, "wb") as file:
                file.write(requests.get(image_src).content)
        except Exception as e:
            print(f"Error saving image: {e}")

extract_images(get_html(f"https://dawmac.eu/epages/950018285.sf/en_GB/?ObjectPath=/Shops/950018285/Categories/Nasi_klienci/{car_brand}"))