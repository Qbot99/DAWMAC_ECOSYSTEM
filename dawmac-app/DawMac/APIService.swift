//
//  APIService.swift
//  DawMac
//
//  Created by Hubert Kubot on 27/01/2026.
//

import Foundation
import UIKit

// --- PROSTY MANAGER PAMIĘCI PODRĘCZNEJ (NSCache) ---
class ImageCache {
    static let shared = ImageCache()
    let cache: NSCache<NSString, UIImage> = {
        let cache = NSCache<NSString, UIImage>()
        cache.countLimit = 200 // Maksymalnie 200 obrazków w pamięci RAM
        cache.totalCostLimit = 1024 * 1024 * 150 // Limit RAM: ~150 MB
        return cache
    }()
}

class APIService {
    static let shared = APIService() // Singleton
    
    let baseURL = "https://api.dawmacpolska.pl"
    let session: URLSession
    
    private init() {
        let config = URLSessionConfiguration.default
        config.timeoutIntervalForRequest = 60
        config.requestCachePolicy = .useProtocolCachePolicy
        config.urlCache = nil
        self.session = URLSession(configuration: config)
    }

    // --- 1. NAPRAWA LINKÓW (Pobieranie) ---
    func safeURL(string: String?) -> URL? {
        guard let string = string, !string.isEmpty else { return nil }
        var clean = string.replacingOccurrences(of: " ", with: "%20")
        
        if clean.lowercased().hasPrefix("http") { return URL(string: clean) }
        while clean.hasPrefix("/") || clean.hasPrefix(".") { clean = String(clean.dropFirst()) }
        
        var finalString = ""
        if clean.hasPrefix("images/") { finalString = "\(baseURL)/gallery/\(clean)" }
        else if clean.contains("wheels_images/") { finalString = "\(baseURL)/forged/\(clean)" }
        else { finalString = "\(baseURL)/\(clean)" }
        
        return URL(string: finalString)
    }

    // --- 2. POBIERANIE OBRAZKA (Z CACHE I OPTYMALIZACJĄ RAM) ---
    func fetchImage(url: URL, completion: @escaping (UIImage?) -> Void) {
        let cacheKey = url.absoluteString
        
        // 1. Sprawdzamy RAM
        if let cachedImage = ImageCache.shared.cache.object(forKey: NSString(string: cacheKey)) {
            completion(cachedImage)
            return
        }
        
        // 2. Jeśli nie ma, pobieramy
        let request = URLRequest(url: url, cachePolicy: .returnCacheDataElseLoad, timeoutInterval: 30)
        session.dataTask(with: request) { data, _, _ in
            if let data = data {
                // Downsampling do 1200px (żyleta na ekranie, ułamek zużycia RAM)
                if let optimizedImage = UIImage.downsample(imageData: data, to: 1200) {
                    ImageCache.shared.cache.setObject(optimizedImage, forKey: NSString(string: cacheKey))
                    DispatchQueue.main.async { completion(optimizedImage) }
                } else if let image = UIImage(data: data) {
                    ImageCache.shared.cache.setObject(image, forKey: NSString(string: cacheKey))
                    DispatchQueue.main.async { completion(image) }
                } else {
                    DispatchQueue.main.async { completion(nil) }
                }
            } else {
                DispatchQueue.main.async { completion(nil) }
            }
        }.resume()
    }

    // --- 3. POBIERANIE DANYCH (JSON) ---
    func fetchData<T: Decodable>(endpoint: String, completion: @escaping (Result<T, Error>) -> Void) {
        guard let url = URL(string: "\(baseURL)/ios/\(endpoint)") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue("Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)", forHTTPHeaderField: "User-Agent")
        
        session.dataTask(with: request) { data, _, error in
            if let error = error { completion(.failure(error)); return }
            guard let data = data else { return }
            do {
                let decoded = try JSONDecoder().decode(T.self, from: data)
                completion(.success(decoded))
            } catch { completion(.failure(error)) }
        }.resume()
    }
    
    // --- 4. WYSYŁANIE (UPLOAD) - W TLE, ŻEBY NIE ZACINAŁO UI ---
    func upload(endpoint: String, params: [String: String], image: UIImage?, completion: @escaping (Result<String, Error>) -> Void) {
        guard let url = URL(string: "\(baseURL)/ios/\(endpoint)") else { return }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        let boundary = "Boundary-\(UUID().uuidString)"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        
        DispatchQueue.global(qos: .userInitiated).async {
            var body = Data()
            for (key, value) in params {
                body.append("--\(boundary)\r\nContent-Disposition: form-data; name=\"\(key)\"\r\n\r\n\(value)\r\n".data(using: .utf8)!)
            }
            
            if let originalImage = image {
                let safeImage = originalImage.fixOrientationAndResize(maxDimension: 1920)
                if let imageData = safeImage.jpegData(compressionQuality: 0.7) {
                    let uniqueName = "\(UUID().uuidString).jpg"
                    body.append("--\(boundary)\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"\(uniqueName)\"\r\nContent-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
                    body.append(imageData)
                    body.append("\r\n".data(using: .utf8)!)
                }
            }
            
            body.append("--\(boundary)--\r\n".data(using: .utf8)!)
            request.httpBody = body
            
            self.session.dataTask(with: request) { data, _, error in
                if let error = error {
                    DispatchQueue.main.async { completion(.failure(error)) }
                    return
                }
                let response = String(data: data ?? Data(), encoding: .utf8) ?? ""
                DispatchQueue.main.async { completion(.success(response)) }
            }.resume()
        }
    }
    
    // --- 5. MULTI UPLOAD ---
    func uploadMultipleImages(endpoint: String, params: [String: String], images: [UIImage], completion: @escaping () -> Void) {
        let group = DispatchGroup()
        for img in images {
            group.enter()
            upload(endpoint: endpoint, params: params, image: img) { _ in group.leave() }
        }
        group.notify(queue: .main) { completion() }
    }
}
