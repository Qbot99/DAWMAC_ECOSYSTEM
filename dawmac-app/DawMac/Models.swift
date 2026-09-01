import Foundation

// --- HELPER ---
struct FlexibleID: Codable, Hashable {
    let value: String
    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if let intVal = try? container.decode(Int.self) { self.value = String(intVal) }
        else if let strVal = try? container.decode(String.self) { self.value = strVal }
        else { self.value = UUID().uuidString }
    }
}

// --- DANE AUTA (GALLERY) ---
struct Brand: Codable, Identifiable, Hashable { let id: FlexibleID; let name: String }
struct CarModel: Codable, Identifiable, Hashable { let id: FlexibleID; let name: String; let car_brand_id: FlexibleID? }

struct ProjectItem: Codable, Identifiable {
    let project_id: FlexibleID; let brand_name: String?; let model_name: String?
    let wheel_brand: String?; let wheel_model: String?; let wheel_params: String?
    let image_url: String?
    var galleryImages: [String]? = []
    
    var id: String { project_id.value }
    var searchString: String { "\(brand_name ?? "") \(model_name ?? "") \(wheel_brand ?? "") \(wheel_model ?? "")".lowercased() }
    
    // --- INTELIGENTNA ŚCIEŻKA DLA AUTA ---
    var correctedURL: String? {
        guard let url = image_url, !url.isEmpty else { return nil }
        // Jeśli nie ma ukośnika, to znaczy że to sama nazwa pliku -> dodaj folder aut
        if !url.contains("/") { return "gallery/images/" + url }
        return url
    }
    
    // To samo dla galerii
    var correctedGalleryImages: [String] {
        guard let images = galleryImages else { return [] }
        return images.map { img -> String in
            if !img.contains("/") { return "gallery/images/" + img }
            return img
        }
    }
}

// --- DANE FELGI (FORGED) ---
struct WheelItem: Codable, Identifiable {
    let id: FlexibleID; let name: String; let series_id: FlexibleID?; let description: String?; let image_url: String?
    var galleryImages: [String]? = []
    
    var idString: String { id.value }
    var searchString: String { name.lowercased() }
    
    // --- INTELIGENTNA ŚCIEŻKA DLA FELGI ---
    var correctedURL: String? {
        guard let url = image_url, !url.isEmpty else { return nil }
        // Jeśli nie ma ukośnika, to znaczy że to sama nazwa pliku -> dodaj folder felg
        if !url.contains("/") { return "forged/wheels_images/" + url }
        return url
    }
    
    var correctedGalleryImages: [String] {
        guard let images = galleryImages else { return [] }
        return images.map { img -> String in
            if !img.contains("/") { return "forged/wheels_images/" + img }
            return img
        }
    }
}

struct APIResponse: Codable { let status: String?; let brands: [Brand]?; let models: [CarModel]? }
struct GalleryListResponse: Codable { let status: String?; let data: [ProjectItem]? }
struct ForgedListResponse: Codable { let status: String?; let data: [WheelItem]? }
