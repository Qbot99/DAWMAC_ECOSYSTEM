//
//  ViewModels.swift
//  DawMac
//
//  Created by Hubert Kubot on 27/01/2026.
//

import Foundation
import SwiftUI

// --- VM DLA AUT (GALLERY) ---
class GalleryViewModel: ObservableObject {
    @Published var projects: [ProjectItem] = []
    @Published var isLoading = false
    @Published var searchText = ""
    @Published var brands: [Brand] = []
    @Published var allModels: [CarModel] = []
    
    // Filtry Marki i Modelu
    @Published var filterBrand: Brand? = nil {
        didSet { filterModel = nil }
    }
    @Published var filterModel: CarModel? = nil
    
    var availableFilterModels: [CarModel] {
        guard let brand = filterBrand else { return [] }
        return allModels.filter { $0.car_brand_id?.value == brand.id.value }
    }
    
    var filteredProjects: [ProjectItem] {
        var result = projects
        
        if let brand = filterBrand {
            result = result.filter { $0.brand_name?.caseInsensitiveCompare(brand.name) == .orderedSame }
        }
        
        if let model = filterModel {
            result = result.filter { $0.model_name?.caseInsensitiveCompare(model.name) == .orderedSame }
        }
        
        if !searchText.isEmpty {
            let lowerSearch = searchText.lowercased()
            result = result.filter { $0.searchString.contains(lowerSearch) }
        }
        
        return result
    }
    
    func updateImagesOrder(projectID: String, newOrder: [String]) {
        let cleanNames = newOrder.map { $0.components(separatedBy: "/").last ?? $0 }
        let params = [
            "project_id": projectID,
            "new_order": cleanNames.joined(separator: ",")
        ]
        APIService.shared.upload(endpoint: "api_gallery.php?action=update_order", params: params, image: nil) { _ in }
    }
    
    func fetchProjects() {
        isLoading = true
        APIService.shared.fetchData(endpoint: "api_gallery.php?action=list") { (result: Result<GalleryListResponse, Error>) in
            DispatchQueue.main.async {
                self.isLoading = false
                if case .success(let response) = result { self.processProjects(response.data ?? []) }
            }
        }
    }
    
    func fetchFormData() {
        APIService.shared.fetchData(endpoint: "api_gallery.php?action=get_data") { (result: Result<APIResponse, Error>) in
            DispatchQueue.main.async {
                if case .success(let response) = result {
                    self.brands = response.brands ?? []; self.allModels = response.models ?? []
                }
            }
        }
    }
    
    private func processProjects(_ rawData: [ProjectItem]) {
        var groupedDict: [String: ProjectItem] = [:]; var order: [String] = []
        for var item in rawData {
            if groupedDict[item.id] == nil {
                item.galleryImages = []
                if let url = item.image_url, !url.isEmpty { item.galleryImages?.append(url) }
                groupedDict[item.id] = item; order.append(item.id)
            } else {
                if let url = item.image_url, !url.isEmpty { groupedDict[item.id]?.galleryImages?.append(url) }
            }
        }
        self.projects = order.compactMap { groupedDict[$0] }
    }
    
    func deleteProject(id: String) {
        let endpoint = "api_gallery.php?action=delete&id=\(id)"
        guard let url = URL(string: "\(APIService.shared.baseURL)/ios/\(endpoint)") else { return }
        URLSession.shared.dataTask(with: url) { _,_,_ in
            DispatchQueue.main.async { self.fetchProjects() }
        }.resume()
    }
    
    func deleteSingleImage(url: String, from projectID: String) {
        let params = ["image_url": url]
        APIService.shared.upload(endpoint: "api_gallery.php?action=delete_image", params: params, image: nil) { _ in }
    }
}

// --- VM DLA FELG (FORGED) ---
class ForgedViewModel: ObservableObject {
    @Published var wheels: [WheelItem] = []
    @Published var isLoading = false
    @Published var searchText = ""
    
    var filteredWheels: [WheelItem] {
        var result = wheels
        if !searchText.isEmpty {
            let lowerSearch = searchText.lowercased()
            result = result.filter { $0.searchString.contains(lowerSearch) }
        }
        return result
    }
    
    func updateImagesOrder(wheelID: String, newOrder: [String]) {
        let cleanNames = newOrder.map { $0.components(separatedBy: "/").last ?? $0 }
        let params = [
            "id": wheelID,
            "new_order": cleanNames.joined(separator: ",")
        ]
        APIService.shared.upload(endpoint: "api_forged.php?action=update_order", params: params, image: nil) { _ in }
    }
    
    func fetchWheels() {
        isLoading = true
        APIService.shared.fetchData(endpoint: "api_forged.php?action=list") { (result: Result<ForgedListResponse, Error>) in
            DispatchQueue.main.async {
                self.isLoading = false
                if case .success(let response) = result { self.processWheels(response.data ?? []) }
            }
        }
    }
    
    private func processWheels(_ rawData: [WheelItem]) {
        var groupedDict: [String: WheelItem] = [:]; var order: [String] = []
        for var item in rawData {
            if groupedDict[item.idString] == nil {
                item.galleryImages = []
                if let url = item.image_url, !url.isEmpty { item.galleryImages?.append(url) }
                groupedDict[item.idString] = item; order.append(item.idString)
            } else {
                if let url = item.image_url, !url.isEmpty { groupedDict[item.idString]?.galleryImages?.append(url) }
            }
        }
        self.wheels = order.compactMap { groupedDict[$0] }
    }
    
    func deleteWheel(id: String) {
        let endpoint = "api_forged.php?action=delete&id=\(id)"
        guard let url = URL(string: "\(APIService.shared.baseURL)/ios/\(endpoint)") else { return }
        URLSession.shared.dataTask(with: url) { _,_,_ in
            DispatchQueue.main.async { self.fetchWheels() }
        }.resume()
    }
    
    func deleteSingleImage(url: String, from wheelID: String) {
        let params = ["image_url": url]
        APIService.shared.upload(endpoint: "api_forged.php?action=delete_image", params: params, image: nil) { _ in }
    }
}

